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
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;

// Обработка добавления пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $login = isset($_POST['login']) ? trim($_POST['login']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : null;
        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : '';

        if (empty($login) || empty($email) || empty($password) || empty($full_name) || empty($role_id)) {
            $error = "Заполните все обязательные поля!";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (login, email, password, full_name, position, phone, school_id, role_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute([
                        $login, $email, $hashed_password, $full_name, $position, $phone, $school_id, $role_id
                ]);

                $_SESSION['success_message'] = "Пользователь успешно добавлен!";
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Пользователь с таким логином или email уже существует!";
                } else {
                    $error = "Ошибка при добавлении пользователя: " . $e->getMessage();
                }
            }
        }
    }
    // Обработка редактирования пользователя
    elseif ($action === 'edit' && $user_id > 0) {
        $login = isset($_POST['login']) ? trim($_POST['login']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $position = isset($_POST['position']) ? trim($_POST['position']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : null;
        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($login) || empty($email) || empty($full_name) || empty($role_id)) {
            $error = "Заполните все обязательные поля!";
        } else {
            try {
                // Если указан новый пароль
                if (!empty($_POST['password'])) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET login = ?, email = ?, password = ?, full_name = ?, position = ?, phone = ?, school_id = ?, role_id = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$login, $email, $hashed_password, $full_name, $position, $phone, $school_id, $role_id, $is_active, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET login = ?, email = ?, full_name = ?, position = ?, phone = ?, school_id = ?, role_id = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$login, $email, $full_name, $position, $phone, $school_id, $role_id, $is_active, $user_id]);
                }

                $_SESSION['success_message'] = "Данные пользователя успешно обновлены!";
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Пользователь с таким логином или email уже существует!";
                } else {
                    $error = "Ошибка при обновлении пользователя: " . $e->getMessage();
                }
            }
        }
    }
}

// Обработка удаления пользователя
if ($action === 'delete' && $user_id > 0) {
    try {
        // Нельзя удалить самого себя
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['error_message'] = "Нельзя удалить свою учетную запись!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['success_message'] = "Пользователь успешно удален!";
        }

        header('Location: users.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при удалении пользователя: " . $e->getMessage();
        header('Location: users.php');
        exit;
    }
}

// Сброс пароля
if ($action === 'reset_password' && $user_id > 0) {
    try {
        $new_password = 'password123'; // Стандартный пароль
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);

        $_SESSION['success_message'] = "Пароль пользователя сброшен на: password123";
        header('Location: users.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Ошибка при сбросе пароля: " . $e->getMessage();
        header('Location: users.php');
        exit;
    }
}

// Получение данных пользователя для редактирования/просмотра
$user_data = null;
if (($action === 'edit' || $action === 'view') && $user_id > 0) {
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, s.full_name as school_name 
                          FROM users u 
                          LEFT JOIN roles r ON u.role_id = r.id 
                          LEFT JOIN schools s ON u.school_id = s.id 
                          WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        $_SESSION['error_message'] = "Пользователь не найден!";
        header('Location: users.php');
        exit;
    }
}

// Получение списков для форм
$schools = $pdo->query("SELECT id, full_name FROM schools ORDER BY full_name")->fetchAll();
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll();

// Получение списка пользователей
$where_clause = "";
$params = [];

if ($school_id > 0) {
    $where_clause = "WHERE u.school_id = ?";
    $params[] = $school_id;
}

$users = $pdo->prepare("SELECT u.*, r.name as role_name, s.full_name as school_name 
                       FROM users u 
                       LEFT JOIN roles r ON u.role_id = r.id 
                       LEFT JOIN schools s ON u.school_id = s.id 
                       $where_clause 
                       ORDER BY u.created_at DESC");
$users->execute($params);
$users = $users->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи - Знание Севера</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/users.css">
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
                <li><a href="users.php" class="nav-link active">👥 Пользователи</a></li>
                <li><a href="roles.php" class="nav-link">🔐 Роли и права</a></li>
                <li><a href="curriculum.php" class="nav-link">📚 Учебные планы</a></li>
                <li><a href="academic_periods.php" class="nav-link">📅 Учебные периоды</a></li>
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
                <h1>Управление пользователями</h1>
                <p>Главный Администратор • <?php echo htmlspecialchars($_SESSION['user_login']); ?></p>
            </div>
            <div class="header-actions">
                <?php if ($action === 'add' || $action === 'edit' || $action === 'view'): ?>
                    <a href="users.php<?php echo $school_id ? '?school_id=' . $school_id : ''; ?>" class="btn btn-secondary">← Назад к списку</a>
                <?php else: ?>
                    <a href="users.php?action=add" class="btn btn-primary">➕ Добавить пользователя</a>
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
                <!-- Форма добавления/редактирования пользователя -->
                <div class="form-container">
                    <h2><?php echo $action === 'add' ? 'Добавление пользователя' : 'Редактирование пользователя'; ?></h2>
                    <form method="POST" class="user-form">
                        <div class="form-section">
                            <h3>Основные данные</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Логин *</label>
                                    <input type="text" name="login" value="<?php echo $user_data ? htmlspecialchars($user_data['login']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>ФИО *</label>
                                    <input type="text" name="full_name" value="<?php echo $user_data ? htmlspecialchars($user_data['full_name']) : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Должность</label>
                                    <input type="text" name="position" value="<?php echo $user_data ? htmlspecialchars($user_data['position']) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Безопасность и доступ</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Роль *</label>
                                    <select name="role_id" required>
                                        <option value="">Выберите роль</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($user_data && $user_data['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Школа</label>
                                    <select name="school_id">
                                        <option value="">Без привязки к школе</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>" <?php echo ($user_data && $user_data['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>
                                        <?php echo $action === 'add' ? 'Пароль *' : 'Новый пароль (оставьте пустым чтобы не менять)'; ?>
                                    </label>
                                    <input type="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                                </div>
                                <?php if ($action === 'edit'): ?>
                                    <div class="form-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="is_active" <?php echo $user_data['is_active'] ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            Активный пользователь
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Контактная информация</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Телефон</label>
                                    <input type="tel" name="phone" value="<?php echo $user_data ? htmlspecialchars($user_data['phone']) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? 'Создать пользователя' : 'Обновить данные'; ?>
                            </button>
                            <a href="users.php" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'view' && $user_data): ?>
                <!-- Просмотр пользователя -->
                <div class="view-container">
                    <div class="view-header">
                        <h2><?php echo htmlspecialchars($user_data['full_name']); ?></h2>
                        <div class="view-actions">
                            <a href="users.php?action=edit&id=<?php echo $user_data['id']; ?>" class="btn btn-primary">✏️ Редактировать</a>
                            <button onclick="confirmResetPassword(<?php echo $user_data['id']; ?>)" class="btn btn-warning">🔄 Сбросить пароль</button>
                            <button onclick="confirmDelete(<?php echo $user_data['id']; ?>)" class="btn btn-danger">🗑️ Удалить</button>
                        </div>
                    </div>

                    <div class="view-sections">
                        <div class="view-section">
                            <h3>Основная информация</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>ФИО:</label>
                                    <span><?php echo htmlspecialchars($user_data['full_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Логин:</label>
                                    <span><?php echo htmlspecialchars($user_data['login']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?php echo htmlspecialchars($user_data['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Роль:</label>
                                    <span class="role-badge"><?php echo htmlspecialchars($user_data['role_name']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="view-section">
                            <h3>Работа и контакты</h3>
                            <div class="info-grid">
                                <?php if ($user_data['position']): ?>
                                    <div class="info-item">
                                        <label>Должность:</label>
                                        <span><?php echo htmlspecialchars($user_data['position']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user_data['school_name']): ?>
                                    <div class="info-item">
                                        <label>Школа:</label>
                                        <span><?php echo htmlspecialchars($user_data['school_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user_data['phone']): ?>
                                    <div class="info-item">
                                        <label>Телефон:</label>
                                        <span><?php echo htmlspecialchars($user_data['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="view-section">
                            <h3>Статус и активность</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Статус:</label>
                                    <span class="status-badge status-<?php echo $user_data['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user_data['is_active'] ? 'Активный' : 'Неактивный'; ?>
                                        </span>
                                </div>
                                <div class="info-item">
                                    <label>Дата создания:</label>
                                    <span><?php echo date('d.m.Y H:i', strtotime($user_data['created_at'])); ?></span>
                                </div>
                                <?php if ($user_data['last_login']): ?>
                                    <div class="info-item">
                                        <label>Последний вход:</label>
                                        <span><?php echo date('d.m.Y H:i', strtotime($user_data['last_login'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user_data['updated_at'] != $user_data['created_at']): ?>
                                    <div class="info-item">
                                        <label>Последнее обновление:</label>
                                        <span><?php echo date('d.m.Y H:i', strtotime($user_data['updated_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Фильтры -->
                <div class="filters-section">
                    <div class="filters-header">
                        <h3>Фильтры</h3>
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Школа:</label>
                            <select onchange="filterBySchool(this.value)">
                                <option value="">Все школы</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Список пользователей -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>Список пользователей</h3>
                        <div class="table-actions">
                            <button class="btn btn-sm btn-secondary" onclick="refreshTable()">🔄 Обновить</button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="data-table" id="users-table">
                            <thead>
                            <tr>
                                <th>ФИО</th>
                                <th>Логин</th>
                                <th>Email</th>
                                <th>Роль</th>
                                <th>Школа</th>
                                <th>Статус</th>
                                <th>Дата создания</th>
                                <th>Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        📝 Нет данных о пользователях
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-name">
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                <?php if (!empty($user['position'])): ?>
                                                    <br><small><?php echo htmlspecialchars($user['position']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['login']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="role-badge"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                        </td>
                                        <td><?php echo $user['school_name'] ? htmlspecialchars($user['school_name']) : '—'; ?></td>
                                        <td>
                                                <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $user['is_active'] ? 'Активный' : 'Неактивный'; ?>
                                                </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-edit" title="Редактировать" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    ✏️
                                                </button>
                                                <button class="btn-action btn-view" title="Просмотреть" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                    👁️
                                                </button>
                                                <button class="btn-action btn-reset" title="Сбросить пароль" onclick="confirmResetPassword(<?php echo $user['id']; ?>)">
                                                    🔄
                                                </button>
                                                <button class="btn-action btn-delete" title="Удалить" onclick="confirmDelete(<?php echo $user['id']; ?>)">
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

                    <div class="table-footer">
                        <div class="table-info">
                            Показано <strong><?php echo count($users); ?></strong> пользователей
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="../js/users.js"></script>
</body>
</html>