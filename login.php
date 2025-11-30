<?php
session_start();

// Если пользователь уже авторизован, редиректим на соответствующую страницу
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'super_admin':
            header('Location: admin/super_dashboard.php');
            exit;
        case 'school_admin':
            header('Location: school_admin/dashboard.php');
            exit;
        case 'teacher':
        case 'class_teacher':
            header('Location: teacher/dashboard.php');
            exit;
        case 'student':
            header('Location: student/dashboard.php');
            exit;
        case 'parent':
            header('Location: parent/dashboard.php');
            exit;
        default:
            // Если роль неизвестна, разлогиниваем
            session_unset();
            session_destroy();
            break;
    }
}

// Подключение к базе данных
require_once 'config/database.php';
$pdo = getDatabaseConnection();

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = "Введите логин и пароль";
    } else {
        try {
            // Ищем пользователя по логину или email
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name, s.id as school_id, s.full_name as school_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                LEFT JOIN schools s ON u.school_id = s.id 
                WHERE (u.login = ? OR u.email = ?) AND u.is_active = TRUE
            ");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Успешная авторизация
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_login'] = $user['login'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role_name'];
                $_SESSION['user_school_id'] = $user['school_id'] ?? null; // Добавляем ?? null
                $_SESSION['school_name'] = $user['school_name'] ?? '';
                $_SESSION['last_activity'] = time();

                // Редирект в зависимости от роли
                switch ($user['role_name']) {
                    case 'super_admin':
                        header('Location: admin/super_dashboard.php');
                        exit;
                    case 'school_admin':
                        header('Location: school_admin/dashboard.php');
                        exit;
                    case 'teacher':
                    case 'class_teacher':
                        header('Location: teacher/dashboard.php');
                        exit;
                    case 'student':
                        header('Location: student/dashboard.php');
                        exit;
                    case 'parent':
                        header('Location: parent/dashboard.php');
                        exit;
                    default:
                        $error = "Неизвестная роль пользователя";
                        break;
                }
            } else {
                $error = "Неверный логин или пароль";
            }
        } catch (PDOException $e) {
            $error = "Ошибка при входе в систему";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - Знание Севера</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
            text-align: center;
        }

        .system-info {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }

        .demo-accounts {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 12px;
        }

        .demo-accounts h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .demo-account {
            margin-bottom: 5px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <h1>Знание Севера</h1>
        <p>Электронный дневник</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="login">Логин или Email:</label>
            <input type="text" id="login" name="login" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn-login">Войти в систему</button>
    </form>

    <div class="system-info">
        Система электронного документооборота образовательного учреждения
    </div>
</div>
</body>
</html>