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

// Получаем классы учителя
$classes = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.grade_level 
        FROM classes c 
        JOIN schedule sch ON c.id = sch.class_id 
        WHERE sch.teacher_id = ? AND sch.school_id = ?
        ORDER BY c.grade_level, c.name
    ");
    $stmt->execute([$teacher_id, $school_id]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении классов: " . $e->getMessage());
}

// Обработка генерации отчета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $report_type = $_POST['report_type'];
    $class_id = intval($_POST['class_id']);
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $format = $_POST['format'];

    // Генерация отчета в зависимости от типа
    switch ($report_type) {
        case 'quarter_grades':
            generateQuarterGradesReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format);
            break;
        case 'class_statistics':
            generateClassStatisticsReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format);
            break;
        case 'parent_meeting':
            generateParentMeetingReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format);
            break;
        case 'head_teacher':
            generateHeadTeacherReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format);
            break;
    }
    exit;
}

// Функция генерации отчета за четверть
function generateQuarterGradesReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format) {
    // Получаем данные для отчета
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name as student_name,
            COUNT(g.id) as total_grades,
            AVG(CAST(g.grade_value AS DECIMAL)) as average_grade,
            SUM(CASE WHEN g.grade_value IN ('4','5') THEN 1 ELSE 0 END) as good_grades,
            SUM(CASE WHEN g.grade_value = '2' THEN 1 ELSE 0 END) as bad_grades
        FROM users u
        LEFT JOIN grades g ON u.id = g.student_id 
        WHERE u.class_id = ? 
        AND g.teacher_id = ?
        AND g.lesson_date BETWEEN ? AND ?
        AND g.grade_value REGEXP '^[0-9]+$'
        GROUP BY u.id
        ORDER BY average_grade DESC
    ");
    $stmt->execute([$class_id, $teacher_id, $date_from, $date_to]);
    $grades_data = $stmt->fetchAll();

    // Получаем информацию о классе
    $class_stmt = $pdo->prepare("SELECT name, grade_level FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class_info = $class_stmt->fetch();

    if ($format === 'pdf') {
        generatePDFReport($grades_data, $class_info, 'quarter_grades', $date_from, $date_to);
    } else {
        generateExcelReport($grades_data, $class_info, 'quarter_grades', $date_from, $date_to);
    }
}

// Функция генерации статистики по классу
function generateClassStatisticsReport($pdo, $teacher_id, $class_id, $date_from, $date_to, $format) {
    // Статистика по успеваемости
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.id) as total_students,
            AVG(avg_grades.average) as class_average,
            COUNT(CASE WHEN avg_grades.average >= 4.5 THEN 1 END) as excellent_students,
            COUNT(CASE WHEN avg_grades.average >= 3.5 AND avg_grades.average < 4.5 THEN 1 END) as good_students,
            COUNT(CASE WHEN avg_grades.average < 3.5 THEN 1 END) as satisfactory_students
        FROM users u
        LEFT JOIN (
            SELECT student_id, AVG(CAST(grade_value AS DECIMAL)) as average
            FROM grades 
            WHERE teacher_id = ? 
            AND lesson_date BETWEEN ? AND ?
            AND grade_value REGEXP '^[0-9]+$'
            GROUP BY student_id
        ) avg_grades ON u.id = avg_grades.student_id
        WHERE u.class_id = ?
    ");
    $stmt->execute([$teacher_id, $date_from, $date_to, $class_id]);
    $statistics = $stmt->fetch();

    // Получаем информацию о классе
    $class_stmt = $pdo->prepare("SELECT name, grade_level FROM classes WHERE id = ?");
    $class_stmt->execute([$class_id]);
    $class_info = $class_stmt->fetch();

    if ($format === 'pdf') {
        generatePDFReport([$statistics], $class_info, 'class_statistics', $date_from, $date_to);
    } else {
        generateExcelReport([$statistics], $class_info, 'class_statistics', $date_from, $date_to);
    }
}

// Генерация PDF отчета
function generatePDFReport($data, $class_info, $report_type, $date_from, $date_to) {
    // Для простоты выводим HTML, который можно сохранить как PDF
    header('Content-Type: text/html; charset=utf-8');

    $report_titles = [
        'quarter_grades' => 'Отчет об успеваемости за четверть',
        'class_statistics' => 'Статистика успеваемости класса',
        'parent_meeting' => 'Материалы для родительского собрания',
        'head_teacher' => 'Отчет для завуча'
    ];

    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$report_titles[$report_type]}</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .class-info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .footer { margin-top: 30px; text-align: right; font-size: 0.9em; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>{$report_titles[$report_type]}</h1>
            <div class='class-info'>
                <strong>Класс:</strong> {$class_info['name']} ({$class_info['grade_level']} класс)<br>
                <strong>Период:</strong> " . date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)) . "
            </div>
        </div>
    ";

    // Вывод данных в зависимости от типа отчета
    if ($report_type === 'quarter_grades') {
        echo "<table>
            <tr>
                <th>Ученик</th>
                <th>Кол-во оценок</th>
                <th>Средний балл</th>
                <th>Хорошие оценки (4-5)</th>
                <th>Плохие оценки (2)</th>
            </tr>";
        foreach ($data as $row) {
            echo "<tr>
                <td>{$row['student_name']}</td>
                <td>{$row['total_grades']}</td>
                <td>" . round($row['average_grade'], 2) . "</td>
                <td>{$row['good_grades']}</td>
                <td>{$row['bad_grades']}</td>
            </tr>";
        }
        echo "</table>";
    } elseif ($report_type === 'class_statistics') {
        $stats = $data[0];
        echo "<table>
            <tr><th>Показатель</th><th>Значение</th></tr>
            <tr><td>Всего учеников</td><td>{$stats['total_students']}</td></tr>
            <tr><td>Средний балл по классу</td><td>" . round($stats['class_average'], 2) . "</td></tr>
            <tr><td>Отличников</td><td>{$stats['excellent_students']}</td></tr>
            <tr><td>Хорошистов</td><td>{$stats['good_students']}</td></tr>
            <tr><td>Успевающих</td><td>{$stats['satisfactory_students']}</td></tr>
        </table>";
    }

    echo "
        <div class='footer'>
            Сгенерировано: " . date('d.m.Y H:i') . "
        </div>
    </body>
    </html>
    ";
}

// Генерация Excel отчета
function generateExcelReport($data, $class_info, $report_type, $date_from, $date_to) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.xls"');

    $report_titles = [
        'quarter_grades' => 'Отчет об успеваемости за четверть',
        'class_statistics' => 'Статистика успеваемости класса'
    ];

    echo "<table border='1'>
        <tr><th colspan='5' style='background: #ccc;'>{$report_titles[$report_type]}</th></tr>
        <tr><td colspan='5'>Класс: {$class_info['name']} ({$class_info['grade_level']} класс)</td></tr>
        <tr><td colspan='5'>Период: " . date('d.m.Y', strtotime($date_from)) . " - " . date('d.m.Y', strtotime($date_to)) . "</td></tr>
    ";

    if ($report_type === 'quarter_grades') {
        echo "<tr>
            <th>Ученик</th>
            <th>Кол-во оценок</th>
            <th>Средний балл</th>
            <th>Хорошие оценки</th>
            <th>Плохие оценки</th>
        </tr>";
        foreach ($data as $row) {
            echo "<tr>
                <td>{$row['student_name']}</td>
                <td>{$row['total_grades']}</td>
                <td>" . round($row['average_grade'], 2) . "</td>
                <td>{$row['good_grades']}</td>
                <td>{$row['bad_grades']}</td>
            </tr>";
        }
    }

    echo "</table>";
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расширенные отчеты - Учитель</title>
    <style>
        .reports-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
        }

        .reports-sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .reports-main {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .report-form {
            display: grid;
            gap: 15px;
        }

        .report-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .report-type-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            cursor: pointer;
            transition: all 0.3s;
        }

        .report-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .report-type-card.selected {
            background: #e3f2fd;
            border-left-color: #2196F3;
        }

        .report-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
        }

        .preview-table th,
        .preview-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .preview-table th {
            background: #e9ecef;
        }

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
                <li><a href="schedule.php" class="nav-link">📅 Расписание</a></li>
                <li><a href="calendar.php" class="nav-link">🗓️ Календарь</a></li>
                <li><a href="reports.php" class="nav-link">📈 Отчеты</a></li>
                <li><a href="reports_advanced.php" class="nav-link active">📈 Отчеты2</a></li>
                <li class="nav-section">Общее</li>
                <li><a href="../profile.php" class="nav-link">👤 Профиль</a></li>
                <li><a href="../logout.php" class="nav-link">🚪 Выход</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Расширенные отчеты</h1>
                <p>Генерация аналитических отчетов и статистики</p>
            </div>
        </header>

        <div class="content-body">
            <div class="reports-container">
                <!-- Боковая панель -->
                <div class="reports-sidebar">
                    <h3>⚙️ Настройки отчета</h3>
                    <form method="POST" class="report-form" id="reportForm">
                        <input type="hidden" name="action" value="generate_report">

                        <div class="form-group">
                            <label>Тип отчета:</label>
                            <select name="report_type" id="reportType" required>
                                <option value="quarter_grades">📊 Успеваемость за четверть</option>
                                <option value="class_statistics">📈 Статистика класса</option>
                                <option value="parent_meeting">👥 Для родительского собрания</option>
                                <option value="head_teacher">🎓 Отчет для завуча</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Класс:</label>
                            <select name="class_id" required>
                                <option value="">Выберите класс</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>">
                                        <?= htmlspecialchars($class['name']) ?> (<?= $class['grade_level'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Период с:</label>
                            <input type="date" name="date_from" required value="<?= date('Y-m-01') ?>">
                        </div>

                        <div class="form-group">
                            <label>Период по:</label>
                            <input type="date" name="date_to" required value="<?= date('Y-m-t') ?>">
                        </div>

                        <div class="form-group">
                            <label>Формат:</label>
                            <select name="format" required>
                                <option value="excel">📗 Excel (.xls)</option>
                                <option value="pdf">📘 PDF</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">📥 Сгенерировать отчет</button>
                    </form>

                    <div class="report-types">
                        <div class="report-type-card" onclick="selectReportType('quarter_grades')">
                            <h4>📊 За четверть</h4>
                            <p>Детальная успеваемость по ученикам</p>
                        </div>
                        <div class="report-type-card" onclick="selectReportType('class_statistics')">
                            <h4>📈 Статистика</h4>
                            <p>Общая статистика по классу</p>
                        </div>
                        <div class="report-type-card" onclick="selectReportType('parent_meeting')">
                            <h4>👥 Родителям</h4>
                            <p>Материалы для собрания</p>
                        </div>
                        <div class="report-type-card" onclick="selectReportType('head_teacher')">
                            <h4>🎓 Завучу</h4>
                            <p>Формальный отчет</p>
                        </div>
                    </div>
                </div>

                <!-- Основная область -->
                <div class="reports-main">
                    <h3>👁️ Предпросмотр отчета</h3>
                    <div class="report-preview" id="reportPreview">
                        <p>Выберите тип отчета для просмотра примера</p>
                    </div>

                    <div style="margin-top: 30px;">
                        <h3>📋 История отчетов</h3>
                        <div class="reports-history">
                            <p style="text-align: center; color: #666; padding: 20px;">
                                Здесь будет отображаться история сгенерированных отчетов
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function selectReportType(type) {
        // Убираем выделение со всех карточек
        document.querySelectorAll('.report-type-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Добавляем выделение выбранной карточке
        event.currentTarget.classList.add('selected');

        // Устанавливаем значение в select
        document.getElementById('reportType').value = type;

        // Показываем предпросмотр
        showReportPreview(type);
    }

    function showReportPreview(type) {
        const preview = document.getElementById('reportPreview');
        const previews = {
            'quarter_grades': `
                    <h4>Пример отчета: Успеваемость за четверть</h4>
                    <table class="preview-table">
                        <tr>
                            <th>Ученик</th>
                            <th>Оценок</th>
                            <th>Средний балл</th>
                            <th>4 и 5</th>
                            <th>2</th>
                        </tr>
                        <tr><td>Иванов А.</td><td>15</td><td>4.2</td><td>12</td><td>0</td></tr>
                        <tr><td>Петрова Б.</td><td>14</td><td>3.8</td><td>8</td><td>1</td></tr>
                        <tr><td>Сидоров В.</td><td>16</td><td>4.5</td><td>14</td><td>0</td></tr>
                    </table>
                `,
            'class_statistics': `
                    <h4>Пример отчета: Статистика класса</h4>
                    <table class="preview-table">
                        <tr><th>Показатель</th><th>Значение</th></tr>
                        <tr><td>Всего учеников</td><td>25</td></tr>
                        <tr><td>Средний балл</td><td>4.1</td></tr>
                        <tr><td>Отличников</td><td>8</td></tr>
                        <tr><td>Хорошистов</td><td>12</td></tr>
                        <tr><td>Успевающих</td><td>5</td></tr>
                    </table>
                `,
            'parent_meeting': `
                    <h4>Пример отчета: Для родительского собрания</h4>
                    <p>Будут включены:</p>
                    <ul>
                        <li>Успеваемость класса</li>
                        <li>Проблемные темы</li>
                        <li>Рекомендации родителям</li>
                        <li>Графики прогресса</li>
                    </ul>
                `,
            'head_teacher': `
                    <h4>Пример отчета: Для завуча</h4>
                    <p>Будут включены:</p>
                    <ul>
                        <li>Формальная статистика</li>
                        <li>Выполнение учебного плана</li>
                        <li>Анализ контрольных работ</li>
                        <li>Планы на следующую четверть</li>
                    </ul>
                `
        };

        preview.innerHTML = previews[type] || '<p>Предпросмотр недоступен</p>';
    }

    // Инициализация при загрузке
    document.addEventListener('DOMContentLoaded', function() {
        selectReportType('quarter_grades');
    });
</script>
</body>
</html>