<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

requireTeacher();

$pdo = getDatabaseConnection();
$teacher_id = $_SESSION['user_id'];
$school_id = $_SESSION['user_school_id'];

// Получаем информацию о учителе
$teacher_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();

// Статистика для дашборда
$stats = [
        'total_classes' => 0,
        'total_students' => 0,
        'today_lessons' => 0,
        'pending_grades' => 0
];

try {
    // Количество классов
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT class_id) as count 
        FROM schedule 
        WHERE teacher_id = ? AND school_id = ?
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $stats['total_classes'] = $stmt->fetch()['count'];

    // Количество учеников
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        JOIN schedule s ON u.class_id = s.class_id
        WHERE s.teacher_id = ? AND s.school_id = ? 
        AND u.role_id IN (SELECT id FROM roles WHERE name = 'student')
        AND u.is_active = 1
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $stats['total_students'] = $stmt->fetch()['count'];

    // Уроки на сегодня
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM schedule 
        WHERE teacher_id = ? AND school_id = ? AND lesson_date = CURDATE()
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $stats['today_lessons'] = $stmt->fetch()['count'];

    // Оценки к выставлению (примерно)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT CONCAT(s.class_id, '-', s.subject_id, '-', s.lesson_date)) as count
        FROM schedule s
        LEFT JOIN grades g ON s.class_id = g.subject_id AND s.lesson_date = g.lesson_date
        WHERE s.teacher_id = ? AND s.school_id = ? 
        AND s.lesson_date <= CURDATE()
        AND g.id IS NULL
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $stats['pending_grades'] = $stmt->fetch()['count'];

} catch (PDOException $e) {
    error_log("Ошибка при получении статистики: " . $e->getMessage());
}

// Ближайшие уроки
$upcoming_lessons = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.lesson_date, s.lesson_number, s.room, 
               c.name as class_name, sub.name as subject_name,
               s.class_id, s.subject_id
        FROM schedule s
        JOIN classes c ON s.class_id = c.id
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.teacher_id = ? AND s.school_id = ? 
        AND s.lesson_date >= CURDATE()
        ORDER BY s.lesson_date ASC, s.lesson_number ASC
        LIMIT 5
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $upcoming_lessons = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении ближайших уроков: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная - Учитель</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Сайдбар */
        .sidebar {
            width: 280px;
            background: #2c3e50;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            background: #34495e;
            border-bottom: 1px solid #4a6278;
        }

        .sidebar-header h1 {
            font-size: 1.2em;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 0;
        }

        .user-info {
            padding: 15px 20px;
            background: #34495e;
            border-bottom: 1px solid #4a6278;
        }

        .role-badge {
            display: inline-block;
            background: #e74c3c;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 8px;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
        }

        .nav-section {
            padding: 12px 20px;
            font-size: 0.8em;
            text-transform: uppercase;
            opacity: 0.7;
            border-bottom: 1px solid #4a6278;
        }

        .nav-link {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .nav-link:hover {
            background: #34495e;
            border-left-color: #3498db;
        }

        .nav-link.active {
            background: #34495e;
            border-left-color: #3498db;
            font-weight: bold;
        }

        /* Основной контент */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
        }

        .content-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-title h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .header-title p {
            color: #7f8c8d;
        }

        .content-body {
            padding: 30px;
        }

        /* Статистика */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        /* Секции */
        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .section-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin: 0;
        }

        /* Таблицы */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219653;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state .icon {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-action-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px 20px;
            text-align: center;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s;
            display: block;
        }

        .quick-action-btn:hover {
            border-color: #3498db;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .quick-action-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Боковая панель навигации -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Электронный дневник</h1>
            <p>Учитель</p>
        </div>
        <nav class="sidebar-nav">
            <div class="user-info">
                <strong><?= htmlspecialchars($teacher['full_name']) ?></strong>
                <span class="role-badge">Учитель</span>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active">📊 Главная</a></li>
                <li class="nav-section">Учебный процесс</li>
                <li><a href="grades.php" class="nav-link">📝 Журнал оценок</a></li>
                <li><a href="homework.php" class="nav-link">📚 Домашние задания</a></li>
                <li><a href="schedule.php" class="nav-link">📅 Моё расписание</a></li>
                <li><a href="calendar.php" class="nav-link">🗓️ Календарь</a></li>
                <li><a href="reports.php" class="nav-link">📈 Отчеты</a></li>
                <li><a href="reports_advanced.php" class="nav-link">📈 Отчеты2</a></li>
                <li class="nav-section">Общее</li>
                <li><a href="../profile.php" class="nav-link">👤 Профиль</a></li>
                <li><a href="../logout.php" class="nav-link">🚪 Выход</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Главная панель</h1>
                <p>Добро пожаловать, <?= htmlspecialchars($teacher['full_name']) ?>!</p>
            </div>
        </header>

        <div class="content-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏫</div>
                    <div class="stat-number"><?= $stats['total_classes'] ?></div>
                    <div class="stat-label">Классов</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">👨‍🎓</div>
                    <div class="stat-number"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Учеников</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-number"><?= $stats['today_lessons'] ?></div>
                    <div class="stat-label">Уроков сегодня</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-number"><?= $stats['pending_grades'] ?></div>
                    <div class="stat-label">Оценок к выставлению</div>
                </div>
            </div>

            <!-- Быстрые действия -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">⚡ Быстрые действия</h2>
                </div>

                <div class="quick-actions">
                    <a href="grades.php" class="quick-action-btn">
                        <span class="quick-action-icon">📝</span>
                        <strong>Журнал оценок</strong>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #7f8c8d;">Выставление оценок</p>
                    </a>

                    <a href="homework.php" class="quick-action-btn">
                        <span class="quick-action-icon">📚</span>
                        <strong>Домашние задания</strong>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #7f8c8d;">Добавление ДЗ</p>
                    </a>

                    <a href="schedule.php" class="quick-action-btn">
                        <span class="quick-action-icon">📅</span>
                        <strong>Расписание</strong>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; color: #7f8c8d;">Моё расписание</p>
                    </a>
                </div>
            </div>

            <!-- Ближайшие уроки -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📅 Ближайшие уроки</h2>
                    <a href="schedule.php" class="btn btn-primary">Все уроки</a>
                </div>

                <?php if (!empty($upcoming_lessons)): ?>
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Класс</th>
                            <th>Предмет</th>
                            <th>Урок</th>
                            <th>Кабинет</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($upcoming_lessons as $lesson): ?>
                            <tr>
                                <td><?= date('d.m.Y', strtotime($lesson['lesson_date'])) ?></td>
                                <td><?= htmlspecialchars($lesson['class_name']) ?></td>
                                <td><?= htmlspecialchars($lesson['subject_name']) ?></td>
                                <td><?= $lesson['lesson_number'] ?> урок</td>
                                <td><?= htmlspecialchars($lesson['room']) ?></td>
                                <td>
                                    <a href="grades.php?class_id=<?= $lesson['class_id'] ?>&subject_id=<?= $lesson['subject_id'] ?>" class="btn btn-success" style="padding: 6px 12px; font-size: 0.8em;">
                                        Выставить оценки
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📅</div>
                        <h3>Нет предстоящих уроков</h3>
                        <p>У вас нет запланированных уроков на ближайшее время</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>