<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// Проверка прав - только school_admin
requireSchoolAdmin();

$pdo = getDatabaseConnection();

// Получаем school_id из сессии с проверкой
$school_id = $_SESSION['user_school_id'] ?? null;
if (!$school_id) {
    $_SESSION['error_message'] = "Школа не определена. Обратитесь к администратору.";
    header('Location: dashboard.php');
    exit;
}

// Получаем информацию о школе
$school_stmt = $pdo->prepare("SELECT full_name, short_name FROM schools WHERE id = ?");
$school_stmt->execute([$school_id]);
$school = $school_stmt->fetch();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$grade_type_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Создаем таблицу типов оценок если её нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grade_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            min_score INT NOT NULL DEFAULT 0,
            max_score INT NOT NULL DEFAULT 5,
            description TEXT,
            color VARCHAR(7) DEFAULT '#3498db',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    error_log("Ошибка при создании таблицы grade_types: " . $e->getMessage());
}

// Обработка добавления типа оценки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $min_score = intval($_POST['min_score']);
        $max_score = intval($_POST['max_score']);
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        $color = !empty($_POST['color']) ? trim($_POST['color']) : '#3498db';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Валидация
        $errors = [];

        if (empty($name)) {
            $errors[] = "Введите название типа оценки";
        }

        if ($min_score < 0) {
            $errors[] = "Минимальный балл не может быть отрицательным";
        }

        if ($max_score <= $min_score) {
            $errors[] = "Максимальный балл должен быть больше минимального";
        }

        if (empty($errors)) {
            try {
                // Проверяем, не существует ли уже такого типа оценки
                $stmt = $pdo->prepare("
                    SELECT id FROM grade_types 
                    WHERE school_id = ? AND name = ?
                ");
                $stmt->execute([$school_id, $name]);

                if ($stmt->fetch()) {
                    $errors[] = "Тип оценки с таким названием уже существует";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO grade_types (school_id, name, min_score, max_score, description, color, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $school_id,
                        $name,
                        $min_score,
                        $max_score,
                        $description,
                        $color,
                        $is_active
                    ]);

                    $_SESSION['success_message'] = "Тип оценки успешно добавлен!";
                    header('Location: grade_types.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Ошибка при добавлении типа оценки: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    // Обработка редактирования типа оценки
    elseif ($action === 'edit' && $grade_type_id > 0) {
        $name = trim($_POST['name']);
        $min_score = intval($_POST['min_score']);
        $max_score = intval($_POST['max_score']);
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        $color = !empty($_POST['color']) ? trim($_POST['color']) : '#3498db';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Валидация
        $errors = [];

        if (empty($name)) {
            $errors[] = "Введите название типа оценки";
        }

        if ($min_score < 0) {
            $errors[] = "Минимальный балл не может быть отрицательным";
        }

        if ($max_score <= $min_score) {
            $errors[] = "Максимальный балл должен быть больше минимального";
        }

        if (empty($errors)) {
            try {
                // Проверяем, не существует ли уже такого типа оценки у другого ID
                $stmt = $pdo->prepare("
                    SELECT id FROM grade_types 
                    WHERE school_id = ? AND name = ? AND id != ?
                ");
                $stmt->execute([$school_id, $name, $grade_type_id]);

                if ($stmt->fetch()) {
                    $errors[] = "Тип оценки с таким названием уже существует";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE grade_types 
                        SET name = ?, min_score = ?, max_score = ?, description = ?, color = ?, is_active = ?
                        WHERE id = ? AND school_id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $min_score,
                        $max_score,
                        $description,
                        $color,
                        $is_active,
                        $grade_type_id,
                        $school_id
                    ]);

                    $_SESSION['success_message'] = "Тип оценки успешно обновлен!";
                    header('Location: grade_types.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Ошибка при обновлении типа оценки: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Обработка удаления типа оценки
if ($action === 'delete' && $grade_type_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM grade_types WHERE id = ? AND school_id = ?");
        $stmt->execute([$grade_type_id, $school_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Тип оценки успешно удален!";
        } else {
            $_SESSION['error_message'] = "Тип оценки не найден или у вас нет прав для его удаления";
        }
        header('Location: grade_types.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении типа оценки: " . $e->getMessage();
        header('Location: grade_types.php');
        exit;
    }
}

// Получение данных типа оценки для редактирования/просмотра
$grade_type_data = null;
if (($action === 'edit' || $action === 'view') && $grade_type_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM grade_types 
            WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([$grade_type_id, $school_id]);
        $grade_type_data = $stmt->fetch();

        if (!$grade_type_data) {
            $_SESSION['error_message'] = "Тип оценки не найден!";
            header('Location: grade_types.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при получении данных типа оценки: " . $e->getMessage();
        header('Location: grade_types.php');
        exit;
    }
}

// Получение списка типов оценок
$grade_types = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM grade_types 
        WHERE school_id = ?
        ORDER BY min_score ASC
    ");
    $stmt->execute([$school_id]);
    $grade_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $grade_types = [];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="../logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система оценок - Знание Севера</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .grade-types-container {
            margin-top: 20px;
        }

        .grade-type-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }

        .grade-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .grade-type-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }

        .grade-range {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .score-badge {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }

        .grade-type-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .grade-type-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .table-actions {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            text-decoration: none;
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
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

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
            font-size: 14px;
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
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Знание Севера</h1>
            <p>Электронный дневник</p>
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
                <li><a href="schedule.php" class="nav-link">📅 Расписание</a></li>
                <li><a href="grade_types.php" class="nav-link active">📊 Типы оценок</a></li>
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
                <h1>Система оценок</h1>
                <p><?php echo htmlspecialchars($school['full_name']); ?></p>
            </div>
            <div class="header-actions">
                <a href="grade_types.php?action=add" class="btn btn-primary">➕ Добавить тип оценки</a>
            </div>
        </header>

        <div class="content-body">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- Форма добавления/редактирования типа оценки -->
                <div class="admin-form">
                    <h2><?php echo $action === 'add' ? 'Добавить тип оценки' : 'Редактировать тип оценки'; ?></h2>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Название типа оценки *</label>
                                <input type="text" id="name" name="name"
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($grade_type_data['name']) ? htmlspecialchars($grade_type_data['name']) : ''); ?>"
                                       placeholder="Например: Отлично, Хорошо, Удовлетворительно" required>
                                <small class="form-hint">Полное название типа оценки</small>
                            </div>
                            <div class="form-group">
                                <label for="min_score">Минимальный балл *</label>
                                <input type="number" id="min_score" name="min_score"
                                       value="<?php echo isset($_POST['min_score']) ? htmlspecialchars($_POST['min_score']) : (isset($grade_type_data['min_score']) ? htmlspecialchars($grade_type_data['min_score']) : '0'); ?>"
                                       min="0" max="10" required>
                                <small class="form-hint">Минимальное значение балла</small>
                            </div>
                            <div class="form-group">
                                <label for="max_score">Максимальный балл *</label>
                                <input type="number" id="max_score" name="max_score"
                                       value="<?php echo isset($_POST['max_score']) ? htmlspecialchars($_POST['max_score']) : (isset($grade_type_data['max_score']) ? htmlspecialchars($grade_type_data['max_score']) : '5'); ?>"
                                       min="1" max="10" required>
                                <small class="form-hint">Максимальное значение балла</small>
                            </div>
                            <div class="form-group">
                                <label for="color">Цвет отображения</label>
                                <input type="color" id="color" name="color"
                                       value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : (isset($grade_type_data['color']) ? htmlspecialchars($grade_type_data['color']) : '#3498db'); ?>">
                                <div class="color-preview" id="colorPreview" style="background-color: <?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : (isset($grade_type_data['color']) ? htmlspecialchars($grade_type_data['color']) : '#3498db'); ?>"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Описание</label>
                            <textarea id="description" name="description" rows="4" placeholder="Дополнительная информация о типе оценки..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : (isset($grade_type_data['description']) ? htmlspecialchars($grade_type_data['description']) : ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_active" value="1"
                                    <?php echo (!isset($_POST['is_active']) && $action === 'add') || (isset($_POST['is_active']) && $_POST['is_active']) || (isset($grade_type_data['is_active']) && $grade_type_data['is_active']) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Активный тип оценки
                            </label>
                            <small class="form-hint">Неактивные типы оценок не будут доступны для использования</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '➕ Добавить тип оценки' : '💾 Сохранить изменения'; ?>
                            </button>
                            <a href="grade_types.php" class="btn btn-secondary">❌ Отмена</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'view' && $grade_type_data): ?>
                <!-- Просмотр типа оценки -->
                <div class="admin-form">
                    <h2>Просмотр типа оценки</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Название:</label>
                            <span><?php echo htmlspecialchars($grade_type_data['name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Диапазон баллов:</label>
                            <span><?php echo $grade_type_data['min_score']; ?> - <?php echo $grade_type_data['max_score']; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Цвет:</label>
                            <div class="color-display">
                                <div class="color-box" style="background-color: <?php echo htmlspecialchars($grade_type_data['color']); ?>"></div>
                                <span><?php echo htmlspecialchars($grade_type_data['color']); ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Статус:</label>
                            <span class="status-badge status-<?php echo $grade_type_data['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $grade_type_data['is_active'] ? 'Активен' : 'Неактивен'; ?>
                            </span>
                        </div>
                        <?php if (isset($grade_type_data['created_at'])): ?>
                            <div class="info-item">
                                <label>Дата создания:</label>
                                <span><?php echo date('d.m.Y H:i', strtotime($grade_type_data['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($grade_type_data['description'])): ?>
                        <div class="form-group">
                            <label>Описание:</label>
                            <div class="description-text"><?php echo nl2br(htmlspecialchars($grade_type_data['description'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="grade_types.php?action=edit&id=<?php echo $grade_type_data['id']; ?>" class="btn btn-primary">✏️ Редактировать</a>
                        <a href="grade_types.php" class="btn btn-secondary">← Назад к типам оценок</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Список типов оценок -->
                <div class="grade-types-container">
                    <h2>Типы оценок</h2>

                    <?php if (empty($grade_types)): ?>
                        <div class="empty-state">
                            <p>Типы оценок не настроены</p>
                            <a href="grade_types.php?action=add" class="btn btn-primary">➕ Добавить первый тип оценки</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grade_types as $grade_type): ?>
                            <div class="grade-type-card">
                                <div class="grade-type-header">
                                    <h3 class="grade-type-title"><?php echo htmlspecialchars($grade_type['name']); ?></h3>
                                    <div class="table-actions">
                                        <a href="grade_types.php?action=edit&id=<?php echo $grade_type['id']; ?>" class="btn-action">✏️</a>
                                        <a href="grade_types.php?action=delete&id=<?php echo $grade_type['id']; ?>" class="btn-action" onclick="return confirm('Удалить тип оценки?')">🗑️</a>
                                    </div>
                                </div>

                                <?php if (!empty($grade_type['description'])): ?>
                                    <div class="grade-type-description">
                                        <?php echo htmlspecialchars($grade_type['description']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="grade-type-footer">
                    <span class="status-badge status-<?php echo $grade_type['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $grade_type['is_active'] ? 'Активен' : 'Неактивен'; ?>
                    </span>
                                    <small style="color: #888;">
                                        Создан: <?php echo date('d.m.Y', strtotime($grade_type['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Обновление предпросмотра цвета
    const colorInput = document.getElementById('color');
    if (colorInput) {
        colorInput.addEventListener('input', function(e) {
            const colorPreview = document.getElementById('colorPreview');
            if (colorPreview) {
                colorPreview.style.backgroundColor = e.target.value;
            }
        });
    }
</script>
</body>
</html>