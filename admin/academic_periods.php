<?php
session_start();
require_once '../config/database.php';

// Проверка авторизации
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getDatabaseConnection();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$period_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Обработка добавления учебного периода
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        try {
            // Если устанавливаем текущий период, сбрасываем текущий статус у других периодов этой школы
            if ($is_current) {
                $reset_stmt = $pdo->prepare("UPDATE academic_periods SET is_current = 0 WHERE school_id = ?");
                $reset_stmt->execute([$school_id]);
            }

            $stmt = $pdo->prepare("INSERT INTO academic_periods (name, school_id, start_date, end_date, is_current) 
                                  VALUES (?, ?, ?, ?, ?)");

            $stmt->execute([
                $name, $school_id, $start_date, $end_date, $is_current
            ]);

            $_SESSION['success_message'] = "Учебный период успешно создан!";
            header('Location: academic_periods.php');
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка при создании учебного периода: " . $e->getMessage();
        }
    }
    // Обработка редактирования учебного периода
    elseif ($action === 'edit' && $period_id > 0) {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $is_current = isset($_POST['is_current']) ? 1 : 0;

        try {
            // Если устанавливаем текущий период, сбрасываем текущий статус у других периодов этой школы
            if ($is_current) {
                $reset_stmt = $pdo->prepare("UPDATE academic_periods SET is_current = 0 WHERE school_id = ? AND id != ?");
                $reset_stmt->execute([$school_id, $period_id]);
            }

            $stmt = $pdo->prepare("UPDATE academic_periods SET name = ?, school_id = ?, start_date = ?, end_date = ?, is_current = ? WHERE id = ?");

            $stmt->execute([
                $name, $school_id, $start_date, $end_date, $is_current, $period_id
            ]);

            $_SESSION['success_message'] = "Учебный период успешно обновлен!";
            header('Location: academic_periods.php');
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка при обновлении учебного периода: " . $e->getMessage();
        }
    }
}

// Обработка удаления учебного периода
if ($action === 'delete' && $period_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM academic_periods WHERE id = ?");
        $stmt->execute([$period_id]);

        $_SESSION['success_message'] = "Учебный период успешно удален!";
        header('Location: academic_periods.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении учебного периода: " . $e->getMessage();
        header('Location: academic_periods.php');
        exit;
    }
}

// Обработка установки текущего периода
if ($action === 'set_current' && $period_id > 0) {
    try {
        // Получаем информацию о периоде чтобы узнать school_id
        $stmt = $pdo->prepare("SELECT school_id FROM academic_periods WHERE id = ?");
        $stmt->execute([$period_id]);
        $period = $stmt->fetch();

        if ($period) {
            // Сбрасываем текущий статус у всех периодов этой школы
            $reset_stmt = $pdo->prepare("UPDATE academic_periods SET is_current = 0 WHERE school_id = ?");
            $reset_stmt->execute([$period['school_id']]);

            // Устанавливаем текущий период
            $set_stmt = $pdo->prepare("UPDATE academic_periods SET is_current = 1 WHERE id = ?");
            $set_stmt->execute([$period_id]);

            $_SESSION['success_message'] = "Текущий учебный период установлен!";
        }

        header('Location: academic_periods.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при установке текущего периода: " . $e->getMessage();
        header('Location: academic_periods.php');
        exit;
    }
}

// Получение данных учебного периода для редактирования/просмотра
$period_data = null;
if (($action === 'edit' || $action === 'view') && $period_id > 0) {
    $stmt = $pdo->prepare("SELECT ap.*, s.full_name as school_name 
                          FROM academic_periods ap 
                          LEFT JOIN schools s ON ap.school_id = s.id 
                          WHERE ap.id = ?");
    $stmt->execute([$period_id]);
    $period_data = $stmt->fetch();

    if (!$period_data) {
        $_SESSION['error_message'] = "Учебный период не найден!";
        header('Location: academic_periods.php');
        exit;
    }
}

// Получение списка школ для выпадающего списка
$schools = $pdo->query("SELECT id, full_name FROM schools WHERE status = 'активная' ORDER BY full_name")->fetchAll();

// Получение списка учебных периодов из БД
$sql = "SELECT ap.*, s.full_name as school_name 
        FROM academic_periods ap 
        LEFT JOIN schools s ON ap.school_id = s.id 
        ORDER BY ap.start_date DESC, ap.created_at DESC";
$periods = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учебные периоды - Знание Севера</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/academic_periods.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Боковая панель -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Знание Севера</h1>
            <p>Электронный дневник</p>
        </div>

        <nav class="sidebar-nav">
            <div class="user-info">
                <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                <span class="role-badge"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span>
            </div>

            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">📊 Обзор</a></li>
                <li class="nav-section">Администрирование</li>
                <li><a href="schools.php" class="nav-link">🏫 Учебные заведения</a></li>
                <li><a href="users.php" class="nav-link">👥 Пользователи</a></li>
                <li><a href="roles.php" class="nav-link">🔐 Роли и права</a></li>
                <li><a href="curriculum.php" class="nav-link">📚 Учебные планы</a></li>
                <li><a href="academic_periods.php" class="nav-link active">📅 Учебные периоды</a></li>
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
                <h1>Учебные периоды</h1>
                <p>Главный Администратор • <?php echo htmlspecialchars($_SESSION['user_login']); ?></p>
            </div>
            <div class="header-actions">
                <?php if ($action === 'add' || $action === 'edit' || $action === 'view'): ?>
                    <a href="academic_periods.php" class="btn btn-secondary">← Назад к списку</a>
                <?php else: ?>
                    <a href="academic_periods.php?action=add" class="btn btn-primary">➕ Создать период</a>
                <?php endif; ?>
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
                <!-- Форма добавления/редактирования учебного периода -->
                <div class="period-container">
                    <div class="period-form">
                        <h2><?php echo $action === 'add' ? 'Создание учебного периода' : 'Редактирование учебного периода'; ?></h2>
                        <form method="POST" id="period-form" onsubmit="return validatePeriodForm()">
                            <div class="form-section">
                                <h3>Основная информация</h3>
                                <div class="form-grid">
                                    <div class="form-group required">
                                        <label>Название периода</label>
                                        <input type="text" name="name" value="<?php echo $period_data ? htmlspecialchars($period_data['name']) : ''; ?>"
                                               placeholder="Например: 2024-2025 учебный год" required>
                                    </div>
                                    <div class="form-group required">
                                        <label>Школа</label>
                                        <select name="school_id" required>
                                            <option value="">Выберите школу</option>
                                            <?php foreach ($schools as $school): ?>
                                                <option value="<?php echo $school['id']; ?>"
                                                    <?php echo ($period_data && $period_data['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($school['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Даты периода</h3>
                                <div class="form-grid">
                                    <div class="form-group required">
                                        <label>Дата начала</label>
                                        <input type="date" name="start_date" value="<?php echo $period_data ? $period_data['start_date'] : ''; ?>" required>
                                    </div>
                                    <div class="form-group required">
                                        <label>Дата окончания</label>
                                        <input type="date" name="end_date" value="<?php echo $period_data ? $period_data['end_date'] : ''; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Настройки</h3>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_current" value="1"
                                            <?php echo ($period_data && $period_data['is_current']) ? 'checked' : ''; ?>>
                                        Сделать текущим учебным периодом
                                    </label>
                                    <small class="form-hint">
                                        Текущий период будет использоваться по умолчанию для всех операций в выбранной школе
                                    </small>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $action === 'add' ? 'Создать период' : 'Сохранить изменения'; ?>
                                </button>
                                <a href="academic_periods.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($action === 'view' && $period_data): ?>
                <!-- Просмотр учебного периода -->
                <div class="period-container">
                    <div class="period-header">
                        <h2><?php echo htmlspecialchars($period_data['name']); ?></h2>
                        <div class="period-actions">
                            <a href="academic_periods.php?action=edit&id=<?php echo $period_data['id']; ?>" class="btn btn-primary">✏️ Редактировать</a>
                            <?php if (!$period_data['is_current']): ?>
                                <a href="academic_periods.php?action=set_current&id=<?php echo $period_data['id']; ?>" class="btn btn-success">⭐ Сделать текущим</a>
                            <?php endif; ?>
                            <button onclick="confirmDelete(<?php echo $period_data['id']; ?>)" class="btn btn-danger">🗑️ Удалить</button>
                        </div>
                    </div>

                    <div class="view-sections">
                        <div class="view-section">
                            <h3>Основная информация</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Название:</label>
                                    <span><?php echo htmlspecialchars($period_data['name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Школа:</label>
                                    <span><?php echo htmlspecialchars($period_data['school_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Статус:</label>
                                    <span class="status-badge <?php echo $period_data['is_current'] ? 'status-current' : 'status-archived'; ?>">
                                            <?php echo $period_data['is_current'] ? 'Текущий' : 'Архивный'; ?>
                                        </span>
                                </div>
                            </div>
                        </div>

                        <div class="view-section">
                            <h3>Даты периода</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Дата начала:</label>
                                    <span><?php echo date('d.m.Y', strtotime($period_data['start_date'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Дата окончания:</label>
                                    <span><?php echo date('d.m.Y', strtotime($period_data['end_date'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Продолжительность:</label>
                                    <span>
                                            <?php
                                            $start = new DateTime($period_data['start_date']);
                                            $end = new DateTime($period_data['end_date']);
                                            $interval = $start->diff($end);
                                            echo $interval->days . ' дней';
                                            ?>
                                        </span>
                                </div>
                            </div>
                        </div>

                        <div class="view-section">
                            <h3>Системная информация</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Дата создания:</label>
                                    <span><?php echo date('d.m.Y H:i', strtotime($period_data['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Список учебных периодов -->
                <div class="period-container">
                    <div class="period-filters">
                        <form id="period-filters">
                            <div class="filter-grid">
                                <div class="filter-group">
                                    <label>Школа</label>
                                    <select name="school">
                                        <option value="all">Все школы</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Статус</label>
                                    <select name="status">
                                        <option value="all">Все статусы</option>
                                        <option value="current">Текущие</option>
                                        <option value="archived">Архивные</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Год</label>
                                    <select name="year">
                                        <option value="all">Все годы</option>
                                        <?php
                                        $current_year = date('Y');
                                        for ($year = $current_year - 5; $year <= $current_year + 2; $year++):
                                            ?>
                                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">Применить</button>
                                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">Сбросить</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="period-table">
                        <div class="table-responsive">
                            <table class="period-data-table" id="period-table">
                                <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Школа</th>
                                    <th>Дата начала</th>
                                    <th>Дата окончания</th>
                                    <th>Статус</th>
                                    <th>Дней</th>
                                    <th>Действия</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($periods)): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">
                                            <div class="empty-state-icon">📅</div>
                                            <h3>Учебные периоды не найдены</h3>
                                            <p>Создайте первый учебный период для вашей школы</p>
                                            <a href="academic_periods.php?action=add" class="btn btn-primary">Создать период</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($periods as $period):
                                        $start = new DateTime($period['start_date']);
                                        $end = new DateTime($period['end_date']);
                                        $days = $start->diff($end)->days;
                                        ?>
                                        <tr data-id="<?php echo $period['id']; ?>" data-school-id="<?php echo $period['school_id']; ?>" data-year="<?php echo $start->format('Y'); ?>">
                                            <td>
                                                <div class="period-name"><?php echo htmlspecialchars($period['name']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($period['school_name']); ?></td>
                                            <td>
                                                <div class="date-cell"><?php echo date('d.m.Y', strtotime($period['start_date'])); ?></div>
                                            </td>
                                            <td>
                                                <div class="date-cell"><?php echo date('d.m.Y', strtotime($period['end_date'])); ?></div>
                                            </td>
                                            <td>
                                                        <span class="status-badge <?php echo $period['is_current'] ? 'status-current' : 'status-archived'; ?>">
                                                            <?php echo $period['is_current'] ? 'Текущий' : 'Архивный'; ?>
                                                        </span>
                                            </td>
                                            <td>
                                                <span class="days-count"><?php echo $days; ?></span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn-action btn-edit" title="Редактировать" onclick="editPeriod(<?php echo $period['id']; ?>)">
                                                        ✏️
                                                    </button>
                                                    <button class="btn-action btn-view" title="Просмотреть" onclick="viewPeriod(<?php echo $period['id']; ?>)">
                                                        👁️
                                                    </button>
                                                    <?php if (!$period['is_current']): ?>
                                                        <button class="btn-action btn-current" title="Сделать текущим" onclick="setCurrentPeriod(<?php echo $period['id']; ?>)">
                                                            ⭐
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn-action btn-delete" title="Удалить" onclick="confirmDelete(<?php echo $period['id']; ?>)">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="../js/academic_periods.js"></script>
</body>
</html>