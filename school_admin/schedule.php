<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

requireSchoolAdmin();

$pdo = getDatabaseConnection();
$school_id = $_SESSION['user_school_id'];

// Создаем таблицу subjects если её нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            short_name VARCHAR(20),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id)
        )
    ");

    // Добавляем базовые предметы если их нет
    $check_subjects = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE school_id = ?");
    $check_subjects->execute([$school_id]);
    $subjects_count = $check_subjects->fetch()['count'];

    if ($subjects_count == 0) {
        $base_subjects = [
                ['name' => 'Математика', 'short_name' => 'Матем'],
                ['name' => 'Русский язык', 'short_name' => 'Рус яз'],
                ['name' => 'Литература', 'short_name' => 'Лит-ра'],
                ['name' => 'История', 'short_name' => 'Ист'],
                ['name' => 'Обществознание', 'short_name' => 'Общ'],
                ['name' => 'География', 'short_name' => 'Геогр'],
                ['name' => 'Биология', 'short_name' => 'Биол'],
                ['name' => 'Физика', 'short_name' => 'Физ'],
                ['name' => 'Химия', 'short_name' => 'Хим'],
                ['name' => 'Английский язык', 'short_name' => 'Англ'],
                ['name' => 'Информатика', 'short_name' => 'Инф'],
                ['name' => 'Физкультура', 'short_name' => 'Физ-ра'],
                ['name' => 'Музыка', 'short_name' => 'Муз'],
                ['name' => 'ИЗО', 'short_name' => 'ИЗО'],
                ['name' => 'Технология', 'short_name' => 'Техн']
        ];

        $stmt = $pdo->prepare("INSERT INTO subjects (school_id, name, short_name) VALUES (?, ?, ?)");
        foreach ($base_subjects as $subject) {
            $stmt->execute([$school_id, $subject['name'], $subject['short_name']]);
        }
    }
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы subjects: " . $e->getMessage());
}

// УДАЛЯЕМ старую таблицу schedule и создаем новую с правильной структурой
try {
    // Проверяем существование таблицы
    $table_exists = $pdo->query("SHOW TABLES LIKE 'schedule'")->fetch();

    if ($table_exists) {
        // Проверяем есть ли столбец lesson_date
        $columns = $pdo->query("SHOW COLUMNS FROM schedule LIKE 'lesson_date'")->fetch();
        if (!$columns) {
            // Если столбца нет, удаляем таблицу и создаем заново
            $pdo->exec("DROP TABLE IF EXISTS schedule");
        }
    }

    // Создаем таблицу schedule с правильной структурой
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedule (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT NOT NULL,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            teacher_id INT NOT NULL,
            lesson_date DATE NOT NULL,
            lesson_number INT,
            room VARCHAR(20),
            is_completed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id),
            FOREIGN KEY (class_id) REFERENCES classes(id),
            FOREIGN KEY (subject_id) REFERENCES subjects(id),
            FOREIGN KEY (teacher_id) REFERENCES users(id)
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы schedule: " . $e->getMessage());
    $_SESSION['error_message'] = "Ошибка при настройке таблицы расписания: " . $e->getMessage();
}

// Получаем информацию о школе
$school_stmt = $pdo->prepare("SELECT full_name, short_name FROM schools WHERE id = ?");
$school_stmt->execute([$school_id]);
$school = $school_stmt->fetch();

$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Получаем классы
$classes = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, grade_level FROM classes WHERE school_id = ? ORDER BY grade_level, name");
    $stmt->execute([$school_id]);
    $classes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении классов: " . $e->getMessage());
}

// Получаем предметы
$subjects = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, short_name FROM subjects WHERE school_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$school_id]);
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении предметов: " . $e->getMessage());
}

// Получаем учителей
$teachers = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.school_id = ? AND r.name IN ('teacher', 'class_teacher') AND u.is_active = 1
        ORDER BY u.full_name
    ");
    $stmt->execute([$school_id]);
    $teachers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении учителей: " . $e->getMessage());
}

// Обработка добавления расписания
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $class_id = intval($_POST['class_id']);
        $subject_id = intval($_POST['subject_id']);
        $teacher_id = intval($_POST['teacher_id']);
        $lesson_date = $_POST['lesson_date'];
        $lesson_number = !empty($_POST['lesson_number']) ? intval($_POST['lesson_number']) : null;
        $room = trim($_POST['room'] ?? '');

        // Валидация
        $errors = [];

        if (empty($class_id)) {
            $errors[] = "Выберите класс";
        }

        if (empty($subject_id)) {
            $errors[] = "Выберите предмет";
        }

        if (empty($teacher_id)) {
            $errors[] = "Выберите учителя";
        }

        if (empty($lesson_date)) {
            $errors[] = "Выберите дату урока";
        }

        if (empty($errors)) {
            try {
                // Проверяем, нет ли уже урока в это время
                $check_stmt = $pdo->prepare("
                    SELECT id FROM schedule 
                    WHERE class_id = ? AND lesson_date = ? AND lesson_number = ? AND school_id = ?
                ");
                $check_stmt->execute([$class_id, $lesson_date, $lesson_number, $school_id]);

                if ($check_stmt->fetch()) {
                    $errors[] = "У этого класса уже есть урок в это время";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO schedule (school_id, class_id, subject_id, teacher_id, lesson_date, lesson_number, room) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                            $school_id, $class_id, $subject_id, $teacher_id,
                            $lesson_date, $lesson_number, $room
                    ]);

                    $_SESSION['success_message'] = "Урок успешно добавлен в расписание!";
                    header('Location: schedule.php');
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Ошибка при добавлении урока: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
    }
    // Обработка массового добавления расписания на месяц
    elseif ($action === 'add_month') {
        $class_id = intval($_POST['class_id']);
        $subject_id = intval($_POST['subject_id']);
        $teacher_id = intval($_POST['teacher_id']);
        $month = $_POST['month'];
        $lesson_number = !empty($_POST['lesson_number']) ? intval($_POST['lesson_number']) : null;
        $room = trim($_POST['room'] ?? '');
        $days_of_week = $_POST['days_of_week'] ?? [];

        $errors = [];

        if (empty($class_id)) $errors[] = "Выберите класс";
        if (empty($subject_id)) $errors[] = "Выберите предмет";
        if (empty($teacher_id)) $errors[] = "Выберите учителя";
        if (empty($month)) $errors[] = "Выберите месяц";
        if (empty($days_of_week)) $errors[] = "Выберите дни недели";

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $year = date('Y', strtotime($month . '-01'));
                $month_num = date('m', strtotime($month . '-01'));
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month_num, $year);
                $lessons_added = 0;

                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = sprintf("%d-%02d-%02d", $year, $month_num, $day);
                    $day_of_week = date('N', strtotime($date)); // 1-понедельник, 7-воскресенье

                    if (in_array($day_of_week, $days_of_week)) {
                        // Проверяем, нет ли уже урока в эту дату
                        $check_stmt = $pdo->prepare("
                            SELECT id FROM schedule 
                            WHERE class_id = ? AND lesson_date = ? AND lesson_number = ? AND school_id = ?
                        ");
                        $check_stmt->execute([$class_id, $date, $lesson_number, $school_id]);

                        if (!$check_stmt->fetch()) {
                            $stmt = $pdo->prepare("
                                INSERT INTO schedule (school_id, class_id, subject_id, teacher_id, lesson_date, lesson_number, room) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                    $school_id, $class_id, $subject_id, $teacher_id,
                                    $date, $lesson_number, $room
                            ]);
                            $lessons_added++;
                        }
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = "Расписание успешно добавлено! Добавлено $lessons_added уроков на месяц.";
                header('Location: schedule.php');
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Ошибка при добавлении расписания: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
    }
}

// Обработка удаления урока
if ($action === 'delete' && $schedule_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ? AND school_id = ?");
        $stmt->execute([$schedule_id, $school_id]);

        $_SESSION['success_message'] = "Урок успешно удален из расписания!";
        header('Location: schedule.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении урока: " . $e->getMessage();
        header('Location: schedule.php');
        exit;
    }
}

// Получение расписания для просмотра
$schedule = [];
$filter_class_id = isset($_GET['filter_class_id']) ? intval($_GET['filter_class_id']) : 0;
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');

try {
    // Проверяем существование столбца lesson_date
    $columns = $pdo->query("SHOW COLUMNS FROM schedule LIKE 'lesson_date'")->fetch();

    if (!$columns) {
        throw new Exception("Таблица schedule не имеет столбца lesson_date. Перезагрузите страницу для автоматического исправления.");
    }

    $sql = "
        SELECT 
            s.*,
            c.name as class_name,
            sub.name as subject_name,
            sub.short_name as subject_short,
            u.full_name as teacher_name
        FROM schedule s
        JOIN classes c ON s.class_id = c.id
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN users u ON s.teacher_id = u.id
        WHERE s.school_id = ?
    ";

    $params = [$school_id];

    if ($filter_class_id > 0) {
        $sql .= " AND s.class_id = ?";
        $params[] = $filter_class_id;
    }

    if ($filter_month) {
        $sql .= " AND DATE_FORMAT(s.lesson_date, '%Y-%m') = ?";
        $params[] = $filter_month;
    }

    $sql .= " ORDER BY s.lesson_date, s.lesson_number";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedule = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении расписания: " . $e->getMessage());
    $_SESSION['error_message'] = "Ошибка при загрузке расписания: " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

// Группировка расписания по датам для календаря
$schedule_by_date = [];
foreach ($schedule as $lesson) {
    $date = $lesson['lesson_date'];
    if (!isset($schedule_by_date[$date])) {
        $schedule_by_date[$date] = [];
    }
    $schedule_by_date[$date][] = $lesson;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание - Администратор школы</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .schedule-container {
            margin-top: 20px;
        }

        .calendar-view {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .calendar-day {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .calendar-date {
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #3498db;
        }

        .lesson-item {
            padding: 8px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }

        .lesson-time {
            font-weight: bold;
            color: #2c3e50;
        }

        .lesson-subject {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .days-checkbox {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
        }

        .day-checkbox label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: normal;
            cursor: pointer;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
        }

        .tab.active {
            background: white;
            border-color: #ddd;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Электронный дневник</h1>
            <p>Администратор школы</p>
            <?php if ($school): ?>
                <div class="school-info">
                    <strong><?php echo htmlspecialchars($school['short_name'] ?: $school['full_name']); ?></strong>
                </div>
            <?php endif; ?>
        </div>
        <nav class="sidebar-nav">
            <div class="user-info">
                <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                <span class="role-badge school-admin">Администратор школы</span>
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">🏠 Главная</a></li>
                <li class="nav-section">Управление школой</li>
                <li><a href="classes.php" class="nav-link">👨‍🏫 Классы</a></li>
                <li><a href="teachers.php" class="nav-link">👥 Учителя</a></li>
                <li><a href="students.php" class="nav-link">🎓 Ученики</a></li>
                <li><a href="parents.php" class="nav-link">👨‍👩‍👧‍👦 Родители</a></li>
                <li><a href="schedule.php" class="nav-link active">📅 Расписание</a></li>
                <li><a href="grade_types.php" class="nav-link">📊 Типы оценок</a></li>
                <li><a href="grade_weights.php" class="nav-link">⚖️ Веса оценок</a></li>
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
                <h1>Расписание уроков</h1>
                <p><?php echo htmlspecialchars($school['full_name']); ?></p>
            </div>
            <div class="header-actions">
                <a href="schedule.php?action=add" class="btn btn-primary">➕ Добавить урок</a>
                <a href="schedule.php?action=add_month" class="btn btn-primary">📅 Расписание на месяц</a>
            </div>
        </header>

        <div class="content-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <!-- Фильтры -->
            <div class="filter-section">
                <form method="GET">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Класс:</label>
                            <select name="filter_class_id" onchange="this.form.submit()">
                                <option value="">Все классы</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['id'] ?>" <?= $filter_class_id == $class['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['name']) ?> (<?= $class['grade_level'] ?> класс)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Месяц:</label>
                            <input type="month" name="filter_month" value="<?= $filter_month ?>" onchange="this.form.submit()">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <a href="schedule.php" class="btn btn-secondary">🔄 Сбросить</a>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($action === 'add'): ?>
                <!-- Форма добавления урока -->
                <div class="admin-form">
                    <h2>Добавить урок в расписание</h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Класс *</label>
                                <select name="class_id" required>
                                    <option value="">Выберите класс</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?> (<?= $class['grade_level'] ?> класс)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Предмет *</label>
                                <select name="subject_id" required>
                                    <option value="">Выберите предмет</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Учитель *</label>
                                <select name="teacher_id" required>
                                    <option value="">Выберите учителя</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Дата урока *</label>
                                <input type="date" name="lesson_date" required>
                            </div>
                            <div class="form-group">
                                <label>Номер урока</label>
                                <input type="number" name="lesson_number" min="1" max="8" placeholder="1-8">
                            </div>
                            <div class="form-group">
                                <label>Кабинет</label>
                                <input type="text" name="room" placeholder="Например: 101">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">➕ Добавить урок</button>
                            <a href="schedule.php" class="btn btn-secondary">❌ Отмена</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'add_month'): ?>
                <!-- Форма добавления расписания на месяц -->
                <div class="admin-form">
                    <h2>Добавить расписание на месяц</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_month">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Класс *</label>
                                <select name="class_id" required>
                                    <option value="">Выберите класс</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['name']) ?> (<?= $class['grade_level'] ?> класс)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Предмет *</label>
                                <select name="subject_id" required>
                                    <option value="">Выберите предмет</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Учитель *</label>
                                <select name="teacher_id" required>
                                    <option value="">Выберите учителя</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Месяц *</label>
                                <input type="month" name="month" required>
                            </div>
                            <div class="form-group">
                                <label>Номер урока</label>
                                <input type="number" name="lesson_number" min="1" max="8" placeholder="1-8">
                            </div>
                            <div class="form-group">
                                <label>Кабинет</label>
                                <input type="text" name="room" placeholder="Например: 101">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Дни недели *</label>
                            <div class="days-checkbox">
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="1"> Понедельник</label>
                                </div>
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="2"> Вторник</label>
                                </div>
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="3"> Среда</label>
                                </div>
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="4"> Четверг</label>
                                </div>
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="5"> Пятница</label>
                                </div>
                                <div class="day-checkbox">
                                    <label><input type="checkbox" name="days_of_week[]" value="6"> Суббота</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">📅 Добавить расписание на месяц</button>
                            <a href="schedule.php" class="btn btn-secondary">❌ Отмена</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- Просмотр расписания -->
                <div class="schedule-container">
                    <h2>Расписание на <?= date('F Y', strtotime($filter_month . '-01')) ?></h2>

                    <?php if (empty($schedule_by_date)): ?>
                        <div class="empty-state">
                            <p>📅 Расписание не найдено</p>
                            <p>Добавьте уроки в расписание для отображения</p>
                            <div style="margin-top: 15px;">
                                <a href="schedule.php?action=add" class="btn btn-primary">➕ Добавить урок</a>
                                <a href="schedule.php?action=add_month" class="btn btn-primary">📅 Расписание на месяц</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="calendar-view">
                            <?php foreach ($schedule_by_date as $date => $lessons): ?>
                                <div class="calendar-day">
                                    <div class="calendar-date">
                                        <?= date('d.m.Y (l)', strtotime($date)) ?>
                                    </div>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <div class="lesson-item">
                                            <div class="lesson-time">
                                                <?= $lesson['lesson_number'] ? $lesson['lesson_number'] . ' урок' : 'Урок' ?>
                                                <?= $lesson['room'] ? ' • Каб. ' . htmlspecialchars($lesson['room']) : '' ?>
                                            </div>
                                            <div class="lesson-subject">
                                                <strong><?= htmlspecialchars($lesson['subject_name']) ?></strong>
                                                <br><?= htmlspecialchars($lesson['class_name']) ?>
                                                <br><?= htmlspecialchars($lesson['teacher_name']) ?>
                                            </div>
                                            <div style="margin-top: 5px;">
                                                <a href="schedule.php?action=delete&id=<?= $lesson['id'] ?>"
                                                   class="btn btn-danger"
                                                   style="padding: 2px 6px; font-size: 0.8em;"
                                                   onclick="return confirm('Удалить урок из расписания?')">
                                                    🗑️ Удалить
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Автоматическая установка текущей даты в форме добавления урока
    document.addEventListener('DOMContentLoaded', function() {
        const lessonDateInput = document.querySelector('input[name="lesson_date"]');
        if (lessonDateInput && !lessonDateInput.value) {
            lessonDateInput.value = '<?= date('Y-m-d') ?>';
        }

        const monthInput = document.querySelector('input[name="month"]');
        if (monthInput && !monthInput.value) {
            monthInput.value = '<?= date('Y-m') ?>';
        }
    });
</script>
</body>
</html>