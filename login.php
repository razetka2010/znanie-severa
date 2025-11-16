<?php
session_start();
require_once 'config/database.php';

// Если пользователь уже авторизован, перенаправляем на дашборд
if (isset($_SESSION['user_id'])) {
    $redirect_url = ($_SESSION['user_role'] === 'super_admin') ? 'admin/dashboard.php' : 'dashboard.php';
    header('Location: ' . $redirect_url);
    exit;
}

$pdo = getDatabaseConnection();
$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];

    // Валидация
    if (empty($login) || empty($password)) {
        $error = "Все поля обязательны для заполнения";
    } else {
        try {
            // Ищем пользователя по логину
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name, s.full_name as school_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN schools s ON u.school_id = s.id 
                WHERE u.login = ? AND u.is_active = 1
            ");
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if ($user) {
                // Проверяем поле пароля (может быть password_hash или password)
                $password_field = '';
                if (isset($user['password_hash'])) {
                    $password_field = 'password_hash';
                } elseif (isset($user['password'])) {
                    $password_field = 'password';
                }

                if ($password_field && password_verify($password, $user[$password_field])) {
                    // Обновляем время последнего входа
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                    $update_stmt->execute([$user['id']]);

                    // Записываем информацию о сессии
                    $session_id = session_id();
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];

                    // Создаем таблицу user_sessions если её нет
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS user_sessions (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            session_id VARCHAR(255) NOT NULL,
                            ip_address VARCHAR(45),
                            user_agent TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");

                    $session_stmt = $pdo->prepare("
                        INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $session_stmt->execute([$user['id'], $session_id, $ip_address, $user_agent]);

                    // Сохраняем данные в сессии
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_login'] = $user['login'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['school_id'] = $user['school_id'];

                    // Перенаправляем в зависимости от роли
                    $redirect_url = ($user['role_name'] === 'super_admin') ? 'admin/dashboard.php' : 'dashboard.php';
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    $error = "Неверный логин или пароль";
                }
            } else {
                $error = "Неверный логин или пароль";
            }
        } catch (PDOException $e) {
            $error = "Ошибка при авторизации: " . $e->getMessage();
        }
    }
}

// Получение статистики системы для отображения на странице входа
$stats = [];
try {
    $stats['total_schools'] = $pdo->query("SELECT COUNT(*) as count FROM schools WHERE status = 'активная'")->fetch()['count'];
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch()['count'];
    $stats['total_students'] = $pdo->query("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'student' AND u.is_active = 1")->fetch()['count'];
    $stats['total_teachers'] = $pdo->query("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('teacher', 'class_teacher') AND u.is_active = 1")->fetch()['count'];
} catch (PDOException $e) {
    // Игнорируем ошибки статистики для страницы входа
    $stats = ['total_schools' => 0, 'total_users' => 0, 'total_students' => 0, 'total_teachers' => 0];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Знание Севера</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="login-container">
    <!-- Левая часть - форма входа -->
    <div class="login-form-section">
        <div class="login-header">
            <div class="logo">
                <h1>Знание Севера</h1>
                <p>Электронный дневник</p>
            </div>
        </div>

        <div class="login-form-container">
            <div class="form-wrapper">
                <div class="form-header">
                    <h2>Вход в систему</h2>
                    <p>Введите ваши учетные данные для доступа</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">⚠️</div>
                        <div class="alert-content">
                            <strong>Ошибка:</strong> <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="login">Логин</label>
                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input type="text" id="login" name="login"
                                   value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>"
                                   placeholder="Введите ваш логин"
                                   required
                                   autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <div class="input-group">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password"
                                   placeholder="Введите ваш пароль"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                👁️
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkmark"></span>
                            Запомнить меня
                        </label>
                        <a href="#" class="forgot-password">Забыли пароль?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login">
                        <span class="btn-icon">🚀</span>
                        Войти в систему
                    </button>
                </form>

                <div class="login-footer">
                    <div class="support-info">
                        <h4>Нужна помощь?</h4>
                        <p>Обратитесь к системному администратору вашего учебного заведения</p>
                        <div class="support-contacts">
                            <div class="contact-item">
                                <span class="contact-icon">📧</span>
                                <span>support@znanie-severa.ru</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📞</span>
                                <span>+7 (XXX) XXX-XX-XX</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Правая часть - информация о системе -->
    <div class="login-info-section">
        <div class="info-overlay">
            <div class="info-content">
                <div class="system-stats">
                    <h3>Система "Знание Севера"</h3>
                    <p>Единая образовательная платформа для учебных заведений</p>

                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon">🏫</div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo $stats['total_schools']; ?></span>
                                <span class="stat-label">Учебных заведений</span>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon">👥</div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo $stats['total_users']; ?></span>
                                <span class="stat-label">Пользователей</span>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon">🎓</div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo $stats['total_students']; ?></span>
                                <span class="stat-label">Учеников</span>
                            </div>
                        </div>

                        <div class="stat-item">
                            <div class="stat-icon">👨‍🏫</div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo $stats['total_teachers']; ?></span>
                                <span class="stat-label">Преподавателей</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Возможности системы:</h4>
                    <ul>
                        <li>
                            <span class="feature-icon">📊</span>
                            <span>Электронный журнал и дневник</span>
                        </li>
                        <li>
                            <span class="feature-icon">📚</span>
                            <span>Учебные планы и расписание</span>
                        </li>
                        <li>
                            <span class="feature-icon">📈</span>
                            <span>Аналитика и отчетность</span>
                        </li>
                        <li>
                            <span class="feature-icon">👨‍👩‍👧‍👦</span>
                            <span>Родительский контроль</span>
                        </li>
                        <li>
                            <span class="feature-icon">🔐</span>
                            <span>Безопасное хранение данных</span>
                        </li>
                    </ul>
                </div>

                <div class="system-info">
                    <div class="info-item">
                        <strong>Версия системы:</strong>
                        <span>2.1.0</span>
                    </div>
                    <div class="info-item">
                        <strong>Последнее обновление:</strong>
                        <span><?php echo date('d.m.Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Фоновое изображение -->
        <div class="background-image"></div>
    </div>
</div>

<script>
    // Переключение видимости пароля
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.querySelector('.password-toggle');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.textContent = '🔒';
        } else {
            passwordInput.type = 'password';
            toggleButton.textContent = '👁️';
        }
    }

    // Валидация формы
    document.querySelector('.login-form').addEventListener('submit', function(e) {
        const login = document.getElementById('login').value.trim();
        const password = document.getElementById('password').value;

        if (!login) {
            e.preventDefault();
            showError('Введите логин');
            document.getElementById('login').focus();
            return;
        }

        if (!password) {
            e.preventDefault();
            showError('Введите пароль');
            document.getElementById('password').focus();
            return;
        }
    });

    function showError(message) {
        // Удаляем существующие ошибки
        const existingAlert = document.querySelector('.alert-error');
        if (existingAlert) {
            existingAlert.remove();
        }

        // Создаем новое уведомление об ошибке
        const alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.innerHTML = `
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <strong>Ошибка:</strong> ${message}
                </div>
            `;

        // Вставляем перед формой
        const form = document.querySelector('.login-form');
        form.parentNode.insertBefore(alert, form);

        // Автоматически скрываем через 5 секунд
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    // Анимация появления элементов
    document.addEventListener('DOMContentLoaded', function() {
        const elements = document.querySelectorAll('.form-group, .form-options, .btn-login');
        elements.forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';

            setTimeout(() => {
                element.style.transition = 'all 0.5s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, 100 * index);
        });
    });

    // Фокус на поле логина при загрузке
    document.getElementById('login').focus();
</script>
</body>
</html>