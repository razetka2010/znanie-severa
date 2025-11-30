<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

requireTeacher();

$pdo = getDatabaseConnection();
$teacher_id = $_SESSION['user_id'];
$school_id = $_SESSION['user_school_id'];

// Получаем информацию о учителе
$teacher_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();

// Получаем расписание учителя
$schedule = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as class_name, sub.name as subject_name
        FROM schedule s
        JOIN classes c ON s.class_id = c.id
        JOIN subjects sub ON s.subject_id = sub.id
        WHERE s.teacher_id = ? AND s.school_id = ?
        AND s.lesson_date >= CURDATE()
        ORDER BY s.lesson_date ASC, s.lesson_number ASC
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $schedule = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении расписания: " . $e->getMessage());
}

// Группируем расписание по дням
$schedule_by_date = [];
foreach ($schedule as $lesson) {
    $date = $lesson['lesson_date'];
    if (!isset($schedule_by_date[$date])) {
        $schedule_by_date[$date] = [];
    }
    $schedule_by_date[$date][] = $lesson;
}

// Получаем расписание на сегодня
$today_lessons = [];
$today = date('Y-m-d');
if (isset($schedule_by_date[$today])) {
    $today_lessons = $schedule_by_date[$today];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание - Учитель</title>
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

        .schedule-day {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .day-header {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .day-header.today {
            background: #27ae60;
        }

        .lesson-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
            transition: all 0.3s;
        }

        .lesson-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .lesson-time {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .lesson-actions {
            display: flex;
            gap: 10px;
        }

        .empty-day {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
        }

        .empty-day .icon {
            font-size: 2em;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .today-highlight {
            background: #e8f5e8;
            border-left-color: #27ae60;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
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
                <li><a href="dashboard.php" class="nav-link">📊 Главная</a></li>
                <li class="nav-section">Учебный процесс</li>
                <li><a href="grades.php" class="nav-link">📝 Журнал оценок</a></li>
                <li><a href="homework.php" class="nav-link">📚 Домашние задания</a></li>
                <li><a href="schedule.php" class="nav-link active">📅 Моё расписание</a></li>
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
                <h1>Моё расписание</h1>
                <p>Расписание уроков на ближайшие дни</p>
            </div>
        </header>

        <div class="content-body">
            <!-- Сегодняшние уроки -->
            <?php if (!empty($today_lessons)): ?>
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">🎯 Сегодня (<?= date('d.m.Y') ?>)</h2>
                    </div>

                    <?php foreach ($today_lessons as $lesson): ?>
                        <div class="lesson-card today-highlight">
                            <div class="lesson-header">
                                <div>
                                    <h3 style="margin: 0 0 5px 0; color: #2c3e50;">
                                        <?= $lesson['lesson_number'] ?> урок - <?= htmlspecialchars($lesson['class_name']) ?>
                                    </h3>
                                    <p style="margin: 0; color: #7f8c8d;">
                                        <?= htmlspecialchars($lesson['subject_name']) ?> | Кабинет: <?= htmlspecialchars($lesson['room']) ?>
                                    </p>
                                </div>
                                <div class="lesson-actions">
                                    <a href="grades.php?class_id=<?= $lesson['class_id'] ?>&subject_id=<?= $lesson['subject_id'] ?>" class="btn btn-success">
                                        📝 Оценки
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">🎯 Сегодня (<?= date('d.m.Y') ?>)</h2>
                    </div>
                    <div class="empty-day">
                        <div class="icon">📅</div>
                        <h3>Уроков нет</h3>
                        <p>На сегодня уроков не запланировано</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Будущие уроки -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">📅 Предстоящие уроки</h2>
                </div>

                <?php if (!empty($schedule_by_date)): ?>
                    <?php foreach ($schedule_by_date as $date => $lessons):
                        if ($date === $today) continue; // Пропускаем сегодняшний день, т.к. он уже показан
                        ?>
                        <div class="schedule-day">
                            <div class="day-header <?= $date === $today ? 'today' : '' ?>">
                                <h3 style="margin: 0;"><?= date('d.m.Y (l)', strtotime($date)) ?></h3>
                            </div>

                            <?php foreach ($lessons as $lesson): ?>
                                <div class="lesson-card">
                                    <div class="lesson-header">
                                        <div>
                                            <h4 style="margin: 0 0 5px 0; color: #2c3e50;">
                                                <?= $lesson['lesson_number'] ?> урок - <?= htmlspecialchars($lesson['class_name']) ?>
                                            </h4>
                                            <p style="margin: 0; color: #7f8c8d;">
                                                <?= htmlspecialchars($lesson['subject_name']) ?> | Кабинет: <?= htmlspecialchars($lesson['room']) ?>
                                            </p>
                                        </div>
                                        <div class="lesson-actions">
                                                <span class="lesson-time">
                                                    Урок <?= $lesson['lesson_number'] ?>
                                                </span>
                                            <a href="grades.php?class_id=<?= $lesson['class_id'] ?>&subject_id=<?= $lesson['subject_id'] ?>" class="btn btn-success" style="padding: 6px 12px;">
                                                📝
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📅</div>
                        <h3>Расписание не найдено</h3>
                        <p>У вас нет запланированных уроков на ближайшее время</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>