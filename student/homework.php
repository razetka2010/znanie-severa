<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

requireStudent();

$pdo = getDatabaseConnection();
$student_id = $_SESSION['user_id'];
$school_id = $_SESSION['user_school_id'];

// Получаем информацию о ученике
$student_stmt = $pdo->prepare("
    SELECT u.*, c.name as class_name, c.grade_level 
    FROM users u 
    LEFT JOIN classes c ON u.class_id = c.id 
    WHERE u.id = ?
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch();

// Получаем параметры фильтрации
$filter_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'active';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$show_completed = isset($_GET['show_completed']) ? true : false;

// Получаем все предметы ученика
$subjects = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.name 
        FROM subjects s 
        JOIN schedule sch ON s.id = sch.subject_id 
        WHERE sch.class_id = ? 
        ORDER BY s.name
    ");
    $stmt->execute([$student['class_id']]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении предметов: " . $e->getMessage());
}

// Получаем домашние задания
$homework = [];
$homework_stats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'overdue' => 0,
    'urgent' => 0
];

try {
    $query = "
        SELECT 
            h.*, 
            s.name as subject_name,
            s.id as subject_id,
            u.full_name as teacher_name,
            hc.completed_at,
            hc.student_comment
        FROM homework h 
        JOIN subjects s ON h.subject_id = s.id 
        JOIN users u ON h.teacher_id = u.id 
        LEFT JOIN homework_completion hc ON h.id = hc.homework_id AND hc.student_id = ?
        WHERE h.class_id = ? 
    ";

    $params = [$student_id, $student['class_id']];

    // Фильтр по статусу
    if ($filter_status === 'active') {
        $query .= " AND (hc.completed_at IS NULL OR h.due_date >= CURDATE())";
    } elseif ($filter_status === 'completed') {
        $query .= " AND hc.completed_at IS NOT NULL";
    } elseif ($filter_status === 'overdue') {
        $query .= " AND hc.completed_at IS NULL AND h.due_date < CURDATE()";
    }

    // Фильтр по предмету
    if ($filter_subject > 0) {
        $query .= " AND h.subject_id = ?";
        $params[] = $filter_subject;
    }

    // Фильтр по дате
    if ($filter_date) {
        $query .= " AND h.due_date = ?";
        $params[] = $filter_date;
    }

    $query .= " ORDER BY 
        CASE WHEN hc.completed_at IS NULL AND h.due_date < CURDATE() THEN 0
             WHEN hc.completed_at IS NULL AND h.due_date = CURDATE() THEN 1
             WHEN hc.completed_at IS NULL AND h.due_date = CURDATE() + INTERVAL 1 DAY THEN 2
             WHEN hc.completed_at IS NULL THEN 3
             ELSE 4 END,
        h.due_date ASC, 
        h.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $homework = $stmt->fetchAll();

    // Получаем статистику
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN hc.completed_at IS NOT NULL THEN 1 END) as completed,
            COUNT(CASE WHEN hc.completed_at IS NULL AND h.due_date < CURDATE() THEN 1 END) as overdue,
            COUNT(CASE WHEN hc.completed_at IS NULL AND h.due_date <= CURDATE() + INTERVAL 1 DAY THEN 1 END) as urgent
        FROM homework h 
        LEFT JOIN homework_completion hc ON h.id = hc.homework_id AND hc.student_id = ?
        WHERE h.class_id = ?
    ");
    $stats_stmt->execute([$student_id, $student['class_id']]);
    $stats = $stats_stmt->fetch();

    $homework_stats = [
        'total' => $stats['total'],
        'completed' => $stats['completed'],
        'overdue' => $stats['overdue'],
        'urgent' => $stats['urgent'],
        'active' => $stats['total'] - $stats['completed']
    ];

} catch (PDOException $e) {
    error_log("Ошибка при получении домашних заданий: " . $e->getMessage());
}

// Обработка отметки выполнения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_completed') {
        $homework_id = intval($_POST['homework_id']);
        $student_comment = trim($_POST['student_comment'] ?? '');

        try {
            // Проверяем, существует ли уже запись
            $check_stmt = $pdo->prepare("SELECT * FROM homework_completion WHERE homework_id = ? AND student_id = ?");
            $check_stmt->execute([$homework_id, $student_id]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                // Обновляем существующую запись
                $stmt = $pdo->prepare("UPDATE homework_completion SET completed_at = NOW(), student_comment = ? WHERE homework_id = ? AND student_id = ?");
                $stmt->execute([$student_comment, $homework_id, $student_id]);
            } else {
                // Создаем новую запись
                $stmt = $pdo->prepare("INSERT INTO homework_completion (homework_id, student_id, completed_at, student_comment) VALUES (?, ?, NOW(), ?)");
                $stmt->execute([$homework_id, $student_id, $student_comment]);
            }

            $_SESSION['success_message'] = "Домашнее задание отмечено как выполненное!";
            header('Location: homework.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Ошибка при сохранении: " . $e->getMessage();
            header('Location: homework.php');
            exit;
        }
    }

    if ($_POST['action'] === 'mark_incomplete') {
        $homework_id = intval($_POST['homework_id']);

        try {
            $stmt = $pdo->prepare("DELETE FROM homework_completion WHERE homework_id = ? AND student_id = ?");
            $stmt->execute([$homework_id, $student_id]);

            $_SESSION['success_message'] = "Домашнее задание возвращено в активные!";
            header('Location: homework.php');
            exit;

        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Ошибка при сохранении: " . $e->getMessage();
            header('Location: homework.php');
            exit;
        }
    }
}

// Создаем таблицу выполнения домашних заданий если её нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS homework_completion (
            id INT PRIMARY KEY AUTO_INCREMENT,
            homework_id INT NOT NULL,
            student_id INT NOT NULL,
            completed_at TIMESTAMP NULL,
            student_comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (homework_id) REFERENCES homework(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_homework_student (homework_id, student_id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы homework_completion: " . $e->getMessage());
}

// Получаем ближайшие дедлайны
$upcoming_deadlines = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            h.*, 
            s.name as subject_name,
            u.full_name as teacher_name
        FROM homework h 
        JOIN subjects s ON h.subject_id = s.id 
        JOIN users u ON h.teacher_id = u.id 
        LEFT JOIN homework_completion hc ON h.id = hc.homework_id AND hc.student_id = ?
        WHERE h.class_id = ? 
        AND h.due_date >= CURDATE() 
        AND hc.completed_at IS NULL
        ORDER BY h.due_date ASC 
        LIMIT 5
    ");
    $stmt->execute([$student_id, $student['class_id']]);
    $upcoming_deadlines = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении ближайших дедлайнов: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Домашние задания - Ученик</title>
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

        .user-details {
            margin-top: 10px;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .role-badge {
            display: inline-block;
            background: #27ae60;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-left: 4px solid;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.total { border-left-color: #3498db; }
        .stat-card.active { border-left-color: #f39c12; }
        .stat-card.completed { border-left-color: #27ae60; }
        .stat-card.overdue { border-left-color: #e74c3c; }
        .stat-card.urgent { border-left-color: #e67e22; }

        .stat-card.active .stat-number { color: #f39c12; }
        .stat-card.completed .stat-number { color: #27ae60; }
        .stat-card.overdue .stat-number { color: #e74c3c; }
        .stat-card.urgent .stat-number { color: #e67e22; }

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

        /* Фильтры */
        .filters-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .status-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .status-filter {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            text-decoration: none;
            color: #2c3e50;
            font-size: 0.9em;
        }

        .status-filter.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .status-filter:hover:not(.active) {
            border-color: #3498db;
            color: #3498db;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9em;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9em;
            transition: border-color 0.3s;
            background: white;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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

        /* Домашние задания */
        .homework-list {
            display: grid;
            gap: 20px;
        }

        .homework-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #3498db;
            transition: transform 0.3s;
            position: relative;
        }

        .homework-card:hover {
            transform: translateX(5px);
        }

        .homework-card.overdue {
            border-left-color: #e74c3c;
            background: #fdf2f2;
        }

        .homework-card.urgent {
            border-left-color: #e67e22;
            background: #fef9e7;
        }

        .homework-card.completed {
            border-left-color: #27ae60;
            background: #f0f9f4;
            opacity: 0.8;
        }

        .homework-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .homework-subject {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2em;
        }

        .homework-meta {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .homework-due {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .homework-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-overdue { background: #e74c3c; color: white; }
        .status-urgent { background: #e67e22; color: white; }
        .status-active { background: #3498db; color: white; }
        .status-completed { background: #27ae60; color: white; }

        .homework-teacher {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .homework-description {
            color: #2c3e50;
            line-height: 1.6;
            margin-bottom: 15px;
            white-space: pre-line;
        }

        .homework-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .completion-info {
            background: #e8f5e8;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.9em;
        }

        .completion-date {
            font-weight: 600;
            color: #27ae60;
        }

        .student-comment {
            margin-top: 5px;
            font-style: italic;
            color: #555;
        }

        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
        }

        .modal-title {
            font-size: 1.3em;
            color: #2c3e50;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #7f8c8d;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: #e74c3c;
        }

        /* Кнопки */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s;
            gap: 5px;
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

        .btn-outline {
            background: transparent;
            border: 2px solid #3498db;
            color: #3498db;
        }

        .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        /* Сообщения */
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

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .homework-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }

            .homework-meta {
                flex-direction: column;
                gap: 5px;
                align-items: flex-start;
            }

            .homework-actions {
                flex-direction: column;
            }

            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        .attached-files {
            margin-top: 10px;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px;
            background: #e9ecef;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .upcoming-list {
            display: grid;
            gap: 15px;
        }

        .upcoming-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #3498db;
        }

        .upcoming-item.urgent {
            border-left-color: #e67e22;
            background: #fef9e7;
        }

        .days-left {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .days-left.urgent {
            background: #e67e22;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Боковая панель навигации -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Электронный дневник</h1>
            <p>Ученик</p>
        </div>
        <nav class="sidebar-nav">
            <div class="user-info">
                <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                <span class="role-badge">Ученик</span>
                <div class="user-details">
                    <?= htmlspecialchars($student['class_name']) ?> класс
                </div>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">📊 Главная</a></li>
                <li class="nav-section">Учебный процесс</li>
                <li><a href="grades.php" class="nav-link">📝 Мои оценки</a></li>
                <li><a href="schedule.php" class="nav-link">📅 Расписание</a></li>
                <li><a href="homework.php" class="nav-link active">📚 Домашние задания</a></li>
                <li><a href="subjects.php" class="nav-link">📖 Предметы</a></li>
                <li class="nav-section">Общее</li>
                <li><a href="../profile.php" class="nav-link">👤 Профиль</a></li>
                <li><a href="../logout.php" class="nav-link">🚪 Выход</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Домашние задания</h1>
                <p>Управление и отслеживание домашних заданий</p>
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
                <div class="stat-card total" onclick="setFilter('status', '')">
                    <div class="stat-icon">📚</div>
                    <div class="stat-number"><?= $homework_stats['total'] ?></div>
                    <div class="stat-label">Всего заданий</div>
                </div>

                <div class="stat-card active" onclick="setFilter('status', 'active')">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?= $homework_stats['active'] ?></div>
                    <div class="stat-label">Активные</div>
                </div>

                <div class="stat-card completed" onclick="setFilter('status', 'completed')">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number"><?= $homework_stats['completed'] ?></div>
                    <div class="stat-label">Выполнено</div>
                </div>

                <div class="stat-card overdue" onclick="setFilter('status', 'overdue')">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-number"><?= $homework_stats['overdue'] ?></div>
                    <div class="stat-label">Просрочено</div>
                </div>
            </div>

            <!-- Ближайшие дедлайны -->
            <?php if (!empty($upcoming_deadlines) && $filter_status !== 'completed'): ?>
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">⏰ Ближайшие дедлайны</h2>
                    </div>

                    <div class="upcoming-list">
                        <?php foreach ($upcoming_deadlines as $hw): ?>
                            <?php
                            $due_date = new DateTime($hw['due_date']);
                            $today = new DateTime();
                            $days_diff = $today->diff($due_date)->days;
                            $is_urgent = $days_diff <= 1;
                            ?>
                            <div class="upcoming-item <?= $is_urgent ? 'urgent' : '' ?>">
                                <div>
                                    <strong><?= htmlspecialchars($hw['subject_name']) ?></strong>
                                    <div style="font-size: 0.9em; color: #7f8c8d; margin-top: 2px;">
                                        Сдать до: <?= date('d.m.Y', strtotime($hw['due_date'])) ?>
                                    </div>
                                </div>
                                <div class="days-left <?= $is_urgent ? 'urgent' : '' ?>">
                                    <?php if ($days_diff == 0): ?>
                                        Сегодня
                                    <?php elseif ($days_diff == 1): ?>
                                        Завтра
                                    <?php else: ?>
                                        Через <?= $days_diff ?> дн.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Фильтры -->
            <div class="filters-section">
                <div class="status-filters">
                    <a href="?status=active" class="status-filter <?= $filter_status === 'active' ? 'active' : '' ?>">⏳ Активные</a>
                    <a href="?status=completed" class="status-filter <?= $filter_status === 'completed' ? 'active' : '' ?>">✅ Выполненные</a>
                    <a href="?status=overdue" class="status-filter <?= $filter_status === 'overdue' ? 'active' : '' ?>">⚠️ Просроченные</a>
                    <a href="?" class="status-filter <?= !$filter_status ? 'active' : '' ?>">📚 Все задания</a>
                </div>

                <form method="GET" class="filters-grid">
                    <input type="hidden" name="status" value="<?= $filter_status ?>">

                    <div class="form-group">
                        <label>Предмет:</label>
                        <select name="subject" onchange="this.form.submit()">
                            <option value="">Все предметы</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>" <?= $filter_subject == $subject['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($subject['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Дата сдачи:</label>
                        <input type="date" name="date" value="<?= $filter_date ?>" onchange="this.form.submit()">
                    </div>

                    <div class="form-group">
                        <a href="homework.php" class="btn btn-secondary">Сбросить фильтры</a>
                    </div>
                </form>
            </div>

            <!-- Список домашних заданий -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <?php if ($filter_status === 'active'): ?>
                            ⏳ Активные задания
                        <?php elseif ($filter_status === 'completed'): ?>
                            ✅ Выполненные задания
                        <?php elseif ($filter_status === 'overdue'): ?>
                            ⚠️ Просроченные задания
                        <?php else: ?>
                            📚 Все домашние задания
                        <?php endif; ?>
                    </h2>
                    <span class="btn btn-secondary" style="cursor: default;">
                            Найдено: <?= count($homework) ?>
                        </span>
                </div>

                <?php if (!empty($homework)): ?>
                    <div class="homework-list">
                        <?php foreach ($homework as $hw): ?>
                            <?php
                            $is_completed = !empty($hw['completed_at']);
                            $is_overdue = !$is_completed && strtotime($hw['due_date']) < strtotime(date('Y-m-d'));
                            $is_urgent = !$is_completed && strtotime($hw['due_date']) <= strtotime('+1 day');

                            $status_class = '';
                            $status_text = '';
                            $status_badge = '';

                            if ($is_completed) {
                                $status_class = 'completed';
                                $status_text = 'Выполнено';
                                $status_badge = 'status-completed';
                            } elseif ($is_overdue) {
                                $status_class = 'overdue';
                                $status_text = 'Просрочено';
                                $status_badge = 'status-overdue';
                            } elseif ($is_urgent) {
                                $status_class = 'urgent';
                                $status_text = 'Срочно';
                                $status_badge = 'status-urgent';
                            } else {
                                $status_class = 'active';
                                $status_text = 'Активно';
                                $status_badge = 'status-active';
                            }
                            ?>

                            <div class="homework-card <?= $status_class ?>" id="homework-<?= $hw['id'] ?>">
                                <div class="homework-header">
                                    <div class="homework-subject">
                                        <?= htmlspecialchars($hw['subject_name']) ?>
                                    </div>
                                    <div class="homework-meta">
                                        <div class="homework-due">
                                            📅 Сдать до: <?= date('d.m.Y', strtotime($hw['due_date'])) ?>
                                        </div>
                                        <div class="homework-status <?= $status_badge ?>">
                                            <?= $status_text ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="homework-teacher">
                                    Учитель: <?= htmlspecialchars($hw['teacher_name']) ?>
                                </div>

                                <div class="homework-description">
                                    <?= nl2br(htmlspecialchars($hw['description'])) ?>
                                </div>

                                <?php if ($hw['attached_files']): ?>
                                    <div class="attached-files">
                                        <strong>Прикрепленные файлы:</strong>
                                        <?php
                                        $files = json_decode($hw['attached_files'], true) ?: [];
                                        foreach ($files as $file):
                                            ?>
                                            <div class="file-item">
                                                📎 <a href="../uploads/homework/<?= htmlspecialchars($file['filename']) ?>" download="<?= htmlspecialchars($file['original_name']) ?>">
                                                    <?= htmlspecialchars($file['original_name']) ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="homework-actions">
                                    <?php if (!$is_completed): ?>
                                        <button type="button" class="btn btn-success" onclick="openCompletionModal(<?= $hw['id'] ?>)">
                                            ✅ Отметить выполненным
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="mark_incomplete">
                                            <input type="hidden" name="homework_id" value="<?= $hw['id'] ?>">
                                            <button type="submit" class="btn btn-outline">
                                                ↩️ Вернуть в активные
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="schedule.php?date=<?= $hw['due_date'] ?>" class="btn btn-primary">
                                        📅 Посмотреть расписание
                                    </a>
                                </div>

                                <?php if ($is_completed): ?>
                                    <div class="completion-info">
                                        <div class="completion-date">
                                            ✅ Выполнено: <?= date('d.m.Y H:i', strtotime($hw['completed_at'])) ?>
                                        </div>
                                        <?php if ($hw['student_comment']): ?>
                                            <div class="student-comment">
                                                💬 Ваш комментарий: "<?= htmlspecialchars($hw['student_comment']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">📚</div>
                        <h3>Домашние задания не найдены</h3>
                        <p>Попробуйте изменить параметры фильтрации</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Модальное окно отметки выполнения -->
<div class="modal" id="completionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">✅ Отметить выполнение</h3>
            <button type="button" class="close-modal" onclick="closeCompletionModal()">×</button>
        </div>

        <form method="POST" id="completionForm">
            <input type="hidden" name="action" value="mark_completed">
            <input type="hidden" name="homework_id" id="modalHomeworkId">

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Комментарий (необязательно):</label>
                <textarea name="student_comment" id="studentComment" rows="4"
                          placeholder="Можете добавить комментарий о выполнении задания..."
                          style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeCompletionModal()">Отмена</button>
                <button type="submit" class="btn btn-success">✅ Подтвердить выполнение</button>
            </div>
        </form>
    </div>
</div>

<script>
    function setFilter(type, value) {
        const url = new URL(window.location.href);

        if (type === 'status') {
            if (value) {
                url.searchParams.set('status', value);
            } else {
                url.searchParams.delete('status');
            }
        }

        window.location.href = url.toString();
    }

    function openCompletionModal(homeworkId) {
        document.getElementById('modalHomeworkId').value = homeworkId;
        document.getElementById('studentComment').value = '';
        document.getElementById('completionModal').style.display = 'flex';
    }

    function closeCompletionModal() {
        document.getElementById('completionModal').style.display = 'none';
    }

    // Закрытие модального окна при клике вне его
    document.getElementById('completionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCompletionModal();
        }
    });

    // Закрытие модального окна по ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCompletionModal();
        }
    });

    // Плавная прокрутка к новому заданию после выполнения
    <?php if (isset($_GET['highlight'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const element = document.getElementById('homework-<?= $_GET['highlight'] ?>');
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            element.style.animation = 'pulse 2s infinite';

            // Добавляем CSS анимацию
            const style = document.createElement('style');
            style.textContent = `
                    @keyframes pulse {
                        0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); }
                        70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
                    }
                `;
            document.head.appendChild(style);
        }
    });
    <?php endif; ?>
</script>
</body>
</html>