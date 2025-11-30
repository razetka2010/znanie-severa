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

// Обработка добавления события
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $event_type = $_POST['event_type'];
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO teacher_events (teacher_id, title, description, event_date, event_time, event_type, class_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$teacher_id, $title, $description, $event_date, $event_time, $event_type, $class_id]);

        $_SESSION['success_message'] = "Событие успешно добавлено!";
        header('Location: calendar.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при добавлении события: " . $e->getMessage();
        header('Location: calendar.php');
        exit;
    }
}

// Обработка удаления события
if (isset($_GET['delete_event'])) {
    $event_id = intval($_GET['delete_event']);

    try {
        $stmt = $pdo->prepare("DELETE FROM teacher_events WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$event_id, $teacher_id]);

        $_SESSION['success_message'] = "Событие удалено!";
        header('Location: calendar.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении события: " . $e->getMessage();
        header('Location: calendar.php');
        exit;
    }
}

// Обработка экспорта в Google Calendar
if (isset($_GET['export_google'])) {
    $icalData = exportToICalendar($teacher_id, 'google');

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="teacher_calendar_google.ics"');
    echo $icalData;
    exit;
}

// Обработка экспорта в Outlook
if (isset($_GET['export_outlook'])) {
    $icalData = exportToICalendar($teacher_id, 'outlook');

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="teacher_calendar_outlook.ics"');
    echo $icalData;
    exit;
}

// Обработка экспорта расписания
if (isset($_GET['export_schedule'])) {
    $icalData = exportScheduleToICalendar($teacher_id);

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="teacher_schedule.ics"');
    echo $icalData;
    exit;
}

// Функция экспорта событий в формате iCalendar
function exportToICalendar($teacher_id, $format = 'google') {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT te.*, c.name as class_name 
        FROM teacher_events te 
        LEFT JOIN classes c ON te.class_id = c.id 
        WHERE te.teacher_id = ? 
        ORDER BY te.event_date ASC, te.event_time ASC
    ");
    $stmt->execute([$teacher_id]);
    $events = $stmt->fetchAll();

    $teacher_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $teacher_stmt->execute([$teacher_id]);
    $teacher = $teacher_stmt->fetch();

    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//SchoolDiary//TeacherCalendar//RU\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";

    foreach ($events as $event) {
        $ical .= "BEGIN:VEVENT\r\n";

        $ical .= "UID:" . uniqid() . "@schooldiary\r\n";
        $ical .= "DTSTAMP:" . date('Ymd\THis\Z', strtotime($event['created_at'])) . "\r\n";

        $startDateTime = $event['event_date'];
        if ($event['event_time']) {
            $startDateTime .= ' ' . $event['event_time'];
            $ical .= "DTSTART:" . date('Ymd\THis', strtotime($startDateTime)) . "\r\n";
            $ical .= "DTEND:" . date('Ymd\THis', strtotime($startDateTime . ' +1 hour')) . "\r\n";
        } else {
            $ical .= "DTSTART;VALUE=DATE:" . date('Ymd', strtotime($startDateTime)) . "\r\n";
            $ical .= "DTEND;VALUE=DATE:" . date('Ymd', strtotime($startDateTime . ' +1 day')) . "\r\n";
        }

        $ical .= "SUMMARY:" . escapeICalText($event['title']) . "\r\n";
        if ($event['description']) {
            $ical .= "DESCRIPTION:" . escapeICalText($event['description']) . "\r\n";
        }

        if ($event['class_name']) {
            $ical .= "LOCATION:" . escapeICalText("Класс: " . $event['class_name']) . "\r\n";
        }

        $categories = [
                'lesson' => 'Урок',
                'meeting' => 'Совещание',
                'exam' => 'Контрольная',
                'event' => 'Мероприятие',
                'reminder' => 'Напоминание'
        ];
        $ical .= "CATEGORIES:" . ($categories[$event['event_type']] ?? 'Событие') . "\r\n";

        $ical .= "STATUS:CONFIRMED\r\n";

        $priority = in_array($event['event_type'], ['lesson', 'exam']) ? 1 : 5;
        $ical .= "PRIORITY:" . $priority . "\r\n";

        if ($teacher['email']) {
            $ical .= "ORGANIZER;CN=\"" . escapeICalText($teacher['full_name']) . "\":mailto:" . $teacher['email'] . "\r\n";
        }

        if ($event['event_time']) {
            $ical .= "BEGIN:VALARM\r\n";
            $ical .= "TRIGGER:-PT15M\r\n";
            $ical .= "ACTION:DISPLAY\r\n";
            $ical .= "DESCRIPTION:Напоминание\r\n";
            $ical .= "END:VALARM\r\n";
        } else {
            $ical .= "BEGIN:VALARM\r\n";
            $ical .= "TRIGGER:-P1D\r\n";
            $ical .= "ACTION:DISPLAY\r\n";
            $ical .= "DESCRIPTION:Напоминание\r\n";
            $ical .= "END:VALARM\r\n";
        }

        $ical .= "END:VEVENT\r\n";
    }

    $ical .= "END:VCALENDAR\r\n";

    return $ical;
}

// Функция экспорта расписания в .ics
function exportScheduleToICalendar($teacher_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT s.*, c.name as class_name, sub.name as subject_name 
        FROM schedule s 
        JOIN classes c ON s.class_id = c.id 
        JOIN subjects sub ON s.subject_id = sub.id 
        WHERE s.teacher_id = ? AND s.school_id = ? 
        AND s.lesson_date >= CURDATE() 
        ORDER BY s.lesson_date ASC, s.lesson_number ASC
    ");
    $stmt->execute([$teacher_id, $_SESSION['user_school_id']]);
    $schedule = $stmt->fetchAll();

    $teacher_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $teacher_stmt->execute([$teacher_id]);
    $teacher = $teacher_stmt->fetch();

    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//SchoolDiary//TeacherSchedule//RU\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";

    $lessonTimes = [
            1 => ['start' => '08:30', 'end' => '09:15'],
            2 => ['start' => '09:25', 'end' => '10:10'],
            3 => ['start' => '10:25', 'end' => '11:10'],
            4 => ['start' => '11:25', 'end' => '12:10'],
            5 => ['start' => '12:20', 'end' => '13:05'],
            6 => ['start' => '13:15', 'end' => '14:00'],
            7 => ['start' => '14:10', 'end' => '14:55']
    ];

    foreach ($schedule as $lesson) {
        $ical .= "BEGIN:VEVENT\r\n";

        $ical .= "UID:lesson_" . $lesson['id'] . "_" . uniqid() . "@schooldiary\r\n";
        $ical .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";

        $lessonNumber = $lesson['lesson_number'];
        $startTime = $lessonTimes[$lessonNumber]['start'] ?? '08:30';
        $endTime = $lessonTimes[$lessonNumber]['end'] ?? '09:15';

        $startDateTime = $lesson['lesson_date'] . ' ' . $startTime;
        $endDateTime = $lesson['lesson_date'] . ' ' . $endTime;

        $ical .= "DTSTART:" . date('Ymd\THis', strtotime($startDateTime)) . "\r\n";
        $ical .= "DTEND:" . date('Ymd\THis', strtotime($endDateTime)) . "\r\n";

        $ical .= "SUMMARY:" . escapeICalText($lesson['subject_name'] . ' - ' . $lesson['class_name']) . "\r\n";
        $ical .= "DESCRIPTION:" . escapeICalText("Урок №" . $lessonNumber . ". Кабинет: " . $lesson['room']) . "\r\n";
        $ical .= "LOCATION:" . escapeICalText("Кабинет " . $lesson['room']) . "\r\n";
        $ical .= "CATEGORIES:Урок\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "PRIORITY:1\r\n";

        if ($teacher['email']) {
            $ical .= "ORGANIZER;CN=\"" . escapeICalText($teacher['full_name']) . "\":mailto:" . $teacher['email'] . "\r\n";
        }

        $ical .= "BEGIN:VALARM\r\n";
        $ical .= "TRIGGER:-PT5M\r\n";
        $ical .= "ACTION:DISPLAY\r\n";
        $ical .= "DESCRIPTION:Скоро урок\r\n";
        $ical .= "END:VALARM\r\n";

        $ical .= "END:VEVENT\r\n";
    }

    $ical .= "END:VCALENDAR\r\n";

    return $ical;
}

// Функция для экранирования текста в формате iCalendar
function escapeICalText($text) {
    $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
    $text = str_replace([',', ';', '\\'], ['\,', '\;', '\\\\'], $text);
    return $text;
}

// Создаем таблицу событий если её нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_events (
            id INT PRIMARY KEY AUTO_INCREMENT,
            teacher_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            event_time TIME,
            event_type ENUM('lesson', 'meeting', 'event', 'reminder', 'exam') DEFAULT 'event',
            class_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            FOREIGN KEY (class_id) REFERENCES classes(id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы teacher_events: " . $e->getMessage());
}

// Получаем события учителя
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT te.*, c.name as class_name 
        FROM teacher_events te 
        LEFT JOIN classes c ON te.class_id = c.id 
        WHERE te.teacher_id = ? 
        ORDER BY te.event_date ASC, te.event_time ASC
    ");
    $stmt->execute([$teacher_id]);
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении событий: " . $e->getMessage());
}

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

// Получаем расписание для календаря
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

// Группируем события по датам для календаря
$events_by_date = [];
foreach ($events as $event) {
    $date = $event['event_date'];
    if (!isset($events_by_date[$date])) {
        $events_by_date[$date] = [];
    }
    $events_by_date[$date][] = $event;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Календарь - Учитель</title>
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

        /* Календарь */
        .calendar-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
        }

        .calendar-sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .calendar-main {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .event-form {
            display: grid;
            gap: 15px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 8px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }

        .calendar-day.today {
            background: #e3f2fd;
        }

        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.9em;
        }

        .event-item {
            font-size: 0.7em;
            padding: 2px 4px;
            margin-bottom: 2px;
            border-radius: 3px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.2s;
        }

        .event-item:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .event-lesson { background: #d4edda; border-left: 3px solid #28a745; }
        .event-meeting { background: #fff3cd; border-left: 3px solid #ffc107; }
        .event-exam { background: #f8d7da; border-left: 3px solid #dc3545; }
        .event-event { background: #cce7ff; border-left: 3px solid #007bff; }
        .event-reminder { background: #e2e3e5; border-left: 3px solid #6c757d; }

        .events-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }

        .event-card {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid;
            transition: transform 0.2s;
        }

        .event-card:hover {
            transform: translateX(5px);
        }

        .event-card.lesson { border-left-color: #28a745; }
        .event-card.meeting { border-left-color: #ffc107; }
        .event-card.exam { border-left-color: #dc3545; }
        .event-card.event { border-left-color: #007bff; }
        .event-card.reminder { border-left-color: #6c757d; }

        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: bold;
            background: #343a40;
            color: white;
            padding: 12px;
            border-radius: 8px 8px 0 0;
        }

        .calendar-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .export-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        /* Формы */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9em;
            transition: border-color 0.3s;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-hint {
            display: block;
            margin-top: 4px;
            color: #7f8c8d;
            font-size: 0.8em;
        }

        /* Кнопки */
        .btn {
            padding: 10px 16px;
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
            transform: translateY(-1px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219653;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .file-actions {
            display: flex;
            gap: 6px;
        }

        .btn-action {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        /* Сообщения */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
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

        /* Модальное окно */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .calendar-container {
                grid-template-columns: 1fr;
            }

            .calendar-sidebar {
                order: 2;
            }

            .calendar-main {
                order: 1;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .calendar-navigation {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .calendar-header,
            .calendar-grid {
                font-size: 0.8em;
            }

            .calendar-day {
                min-height: 80px;
                padding: 4px;
            }

            .event-item {
                font-size: 0.6em;
                padding: 1px 2px;
            }
        }

        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8em;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }

        .stat-badge {
            background: #e3f2fd;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.8em;
        }

        .stat-number {
            font-weight: bold;
            font-size: 1.2em;
            color: #1976d2;
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
                <li><a href="dashboard.php" class="nav-link">📊 Главная</a></li>
                <li class="nav-section">Учебный процесс</li>
                <li><a href="grades.php" class="nav-link">📝 Журнал оценок</a></li>
                <li><a href="homework.php" class="nav-link">📚 Домашние задания</a></li>
                <li><a href="schedule.php" class="nav-link">📅 Расписание</a></li>
                <li><a href="calendar.php" class="nav-link active">🗓️ Календарь</a></li>
                <li><a href="reports.php" class="nav-link">📈 Отчеты</a></li>
                <li class="nav-section">Общее</li>
                <li><a href="../profile.php" class="nav-link">👤 Профиль</a></li>
                <li><a href="../logout.php" class="nav-link">🚪 Выход</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Календарь событий</h1>
                <p>Планирование и управление событиями</p>
            </div>
        </header>

        <div class="content-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <!-- Боковая панель -->
                <div class="calendar-sidebar">
                    <h3 style="margin-bottom: 15px;">➕ Добавить событие</h3>
                    <form method="POST" class="event-form">
                        <input type="hidden" name="action" value="add_event">

                        <div class="form-group">
                            <label>Тип события:</label>
                            <select name="event_type" required>
                                <option value="lesson">📚 Урок</option>
                                <option value="meeting">👥 Собрание</option>
                                <option value="exam">📝 Контрольная</option>
                                <option value="event">🎯 Мероприятие</option>
                                <option value="reminder">⏰ Напоминание</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Название:</label>
                            <input type="text" name="title" required placeholder="Введите название события">
                        </div>

                        <div class="form-group">
                            <label>Описание:</label>
                            <textarea name="description" placeholder="Дополнительная информация" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Дата:</label>
                            <input type="date" name="event_date" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label>Время (опционально):</label>
                            <input type="time" name="event_time">
                            <small class="form-hint">Если не указано, событие будет на весь день</small>
                        </div>

                        <div class="form-group">
                            <label>Класс (если применимо):</label>
                            <select name="class_id">
                                <option value="">-- Не указан --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>">
                                        <?= htmlspecialchars($class['name']) ?> (<?= $class['grade_level'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">📅 Добавить событие</button>
                    </form>

                    <!-- Легенда -->
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #d4edda; border-left: 3px solid #28a745;"></div>
                            <span>Урок</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #fff3cd; border-left: 3px solid #ffc107;"></div>
                            <span>Собрание</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f8d7da; border-left: 3px solid #dc3545;"></div>
                            <span>Контрольная</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #cce7ff; border-left: 3px solid #007bff;"></div>
                            <span>Мероприятие</span>
                        </div>
                    </div>

                    <!-- Статистика -->
                    <div class="quick-stats">
                        <div class="stat-badge">
                            <div class="stat-number"><?= count($events) ?></div>
                            <div>Событий</div>
                        </div>
                        <div class="stat-badge">
                            <div class="stat-number"><?= count($schedule) ?></div>
                            <div>Уроков</div>
                        </div>
                    </div>

                    <!-- Экспорт -->
                    <div class="export-buttons">
                        <div style="font-weight: 600; margin-bottom: 8px;">📤 Экспорт событий:</div>
                        <a href="calendar.php?export_google=1" class="btn btn-primary">
                            📅 Google Calendar
                        </a>
                        <a href="calendar.php?export_outlook=1" class="btn btn-primary">
                            📧 Outlook
                        </a>
                        <div style="font-weight: 600; margin: 15px 0 8px 0;">📚 Экспорт расписания:</div>
                        <a href="calendar.php?export_schedule=1" class="btn btn-success">
                            🗓️ Расписание уроков
                        </a>
                    </div>
                </div>

                <!-- Основной календарь -->
                <div class="calendar-main">
                    <div class="calendar-navigation">
                        <button onclick="changeMonth(-1)" class="btn btn-secondary">◀ Предыдущий</button>
                        <h2 id="current-month" style="margin: 0;"><?= date('F Y') ?></h2>
                        <button onclick="changeMonth(1)" class="btn btn-secondary">Следующий ▶</button>
                    </div>

                    <div class="calendar-header">
                        <div>Пн</div><div>Вт</div><div>Ср</div><div>Чт</div><div>Пт</div><div>Сб</div><div>Вс</div>
                    </div>

                    <div class="calendar-grid" id="calendar-grid">
                        <!-- Календарь будет заполнен через JavaScript -->
                    </div>

                    <div style="margin-top: 25px;">
                        <h3 style="margin-bottom: 15px;">📋 Ближайшие события</h3>
                        <div class="events-list">
                            <?php if (!empty($events)): ?>
                                <?php
                                $upcoming_events = array_filter($events, function($event) {
                                    return strtotime($event['event_date']) >= strtotime(date('Y-m-d'));
                                });
                                $upcoming_events = array_slice($upcoming_events, 0, 8);
                                ?>

                                <?php foreach ($upcoming_events as $event): ?>
                                    <div class="event-card <?= $event['event_type'] ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: start;">
                                            <div style="flex: 1;">
                                                <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                <div style="font-size: 0.8em; color: #666; margin-top: 4px;">
                                                    📅 <?= date('d.m.Y', strtotime($event['event_date'])) ?>
                                                    <?= $event['event_time'] ? ' 🕒 ' . substr($event['event_time'], 0, 5) : '' ?>
                                                    <?= $event['class_name'] ? ' | 👥 ' . $event['class_name'] : '' ?>
                                                </div>
                                                <?php if ($event['description']): ?>
                                                    <div style="font-size: 0.9em; margin-top: 6px; color: #555;">
                                                        <?= htmlspecialchars($event['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button onclick="deleteEvent(<?= $event['id'] ?>)" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.8em;" title="Удалить">
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="icon">📅</div>
                                    <h3>Нет запланированных событий</h3>
                                    <p>Добавьте первое событие используя форму слева</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    let currentDate = new Date();
    const scheduleData = <?= json_encode($schedule) ?>;
    const eventsData = <?= json_encode($events) ?>;

    function renderCalendar() {
        const calendarGrid = document.getElementById('calendar-grid');
        const currentMonthElement = document.getElementById('current-month');

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        currentMonthElement.textContent = currentDate.toLocaleDateString('ru-RU', {
            month: 'long',
            year: 'numeric'
        });

        calendarGrid.innerHTML = '';

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        let firstDayOfWeek = firstDay.getDay();
        if (firstDayOfWeek === 0) firstDayOfWeek = 7;

        for (let i = 1; i < firstDayOfWeek; i++) {
            const dayElement = createDayElement(null, true);
            calendarGrid.appendChild(dayElement);
        }

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayElement = createDayElement(day, false, dateStr);
            calendarGrid.appendChild(dayElement);
        }
    }

    function createDayElement(day, isOtherMonth, dateStr = null) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';

        if (isOtherMonth) {
            dayElement.classList.add('other-month');
            dayElement.innerHTML = `<div class="day-number">${day}</div>`;
        } else {
            dayElement.innerHTML = `<div class="day-number">${day}</div>`;

            const events = getEventsForDate(dateStr);
            events.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.className = `event-item event-${event.type}`;
                eventElement.title = `${event.fullTitle}${event.time ? ' | ' + event.time : ''}${event.room ? ' | ' + event.room : ''}`;
                eventElement.textContent = event.title;
                eventElement.setAttribute('data-event-id', event.id);

                eventElement.addEventListener('click', function(e) {
                    e.stopPropagation();
                    showEventDetails(event);
                });

                dayElement.appendChild(eventElement);
            });

            const today = new Date();
            if (dateStr === today.toISOString().split('T')[0]) {
                dayElement.classList.add('today');
            }

            dayElement.addEventListener('click', function() {
                quickAddEvent(dateStr);
            });
        }

        return dayElement;
    }

    function getEventsForDate(dateStr) {
        const events = [];

        scheduleData.forEach(lesson => {
            const lessonDate = new Date(lesson.lesson_date + 'T00:00:00');
            if (lessonDate.toISOString().split('T')[0] === dateStr) {
                events.push({
                    type: 'lesson',
                    title: `📚 ${lesson.class_name}`,
                    fullTitle: `${lesson.subject_name} - ${lesson.class_name}`,
                    time: getLessonTime(lesson.lesson_number),
                    room: lesson.room,
                    id: 's_' + lesson.id
                });
            }
        });

        eventsData.forEach(event => {
            const eventDate = new Date(event.event_date + 'T00:00:00');
            if (eventDate.toISOString().split('T')[0] === dateStr) {
                const eventIcons = {
                    'lesson': '📚',
                    'meeting': '👥',
                    'exam': '📝',
                    'event': '🎯',
                    'reminder': '⏰'
                };

                const eventTitles = {
                    'lesson': 'Урок',
                    'meeting': 'Совещание',
                    'exam': 'Контрольная',
                    'event': 'Мероприятие',
                    'reminder': 'Напоминание'
                };

                let title = `${eventIcons[event.event_type]} ${eventTitles[event.event_type]}`;
                if (event.class_name) {
                    title += ` (${event.class_name})`;
                }

                events.push({
                    type: event.event_type,
                    title: title,
                    fullTitle: event.title,
                    description: event.description,
                    time: event.event_time ? event.event_time.substring(0, 5) : 'Весь день',
                    class: event.class_name,
                    id: event.id
                });
            }
        });

        events.sort((a, b) => {
            if (a.time === 'Весь день') return -1;
            if (b.time === 'Весь день') return 1;
            return a.time.localeCompare(b.time);
        });

        return events;
    }

    function getLessonTime(lessonNumber) {
        const lessonTimes = {
            1: '08:30',
            2: '09:25',
            3: '10:25',
            4: '11:25',
            5: '12:20',
            6: '13:15',
            7: '14:10'
        };
        return lessonTimes[lessonNumber] || '08:30';
    }

    function showEventDetails(event) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'flex';

        modal.innerHTML = `
                <div class="modal-content">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;">${event.fullTitle}</h3>
                    <div style="display: grid; gap: 10px;">
                        <div><strong>Тип:</strong> ${getEventTypeName(event.type)}</div>
                        ${event.time ? `<div><strong>Время:</strong> ${event.time}</div>` : ''}
                        ${event.room ? `<div><strong>Кабинет:</strong> ${event.room}</div>` : ''}
                        ${event.class ? `<div><strong>Класс:</strong> ${event.class}</div>` : ''}
                        ${event.description ? `<div><strong>Описание:</strong> ${event.description}</div>` : ''}
                    </div>
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        ${event.id && !event.id.startsWith('s_') ?
            `<button onclick="deleteEvent(${event.id})" class="btn btn-danger">🗑️ Удалить</button>` : ''}
                        <button onclick="this.closest('.modal').remove()" class="btn btn-secondary">✕ Закрыть</button>
                    </div>
                </div>
            `;

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });

        document.body.appendChild(modal);
    }

    function getEventTypeName(type) {
        const names = {
            'lesson': '📚 Урок',
            'meeting': '👥 Совещание',
            'exam': '📝 Контрольная работа',
            'event': '🎯 Мероприятие',
            'reminder': '⏰ Напоминание'
        };
        return names[type] || type;
    }

    function quickAddEvent(dateStr) {
        document.querySelector('input[name="event_date"]').value = dateStr;
        document.querySelector('input[name="title"]').focus();

        // Плавная прокрутка к форме
        document.querySelector('.calendar-sidebar').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function changeMonth(direction) {
        currentDate.setMonth(currentDate.getMonth() + direction);
        renderCalendar();
    }

    function deleteEvent(eventId) {
        if (confirm('Вы уверены, что хотите удалить это событие?')) {
            window.location.href = 'calendar.php?delete_event=' + eventId;
        }
    }

    // Инициализация календаря при загрузке
    document.addEventListener('DOMContentLoaded', renderCalendar);

    // Закрытие модального окна по ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal');
            if (modal) {
                modal.remove();
            }
        }
    });
</script>
</body>
</html>
