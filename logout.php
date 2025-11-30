<?php
session_start();

// Сохраняем информацию о пользователе для сообщения
$user_name = $_SESSION['user_name'] ?? 'Пользователь';

// Полностью очищаем все данные сессии
$_SESSION = array();

// Удаляем cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="shortcut icon" href="logo.png" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выход из системы - Знание Севера</title>
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

        .logout-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .logout-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .logout-message {
            margin-bottom: 30px;
        }

        .logout-message h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .logout-message p {
            color: #666;
            line-height: 1.5;
        }

        .btn-login {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .auto-redirect {
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="logout-container">
    <div class="logout-icon">👋</div>

    <div class="logout-message">
        <h1>Выход выполнен</h1>
        <p>Вы успешно вышли из системы.<br>До свидания, <?php echo htmlspecialchars($user_name); ?>!</p>
    </div>

    <a href="login.php" class="btn-login">Войти снова</a>

    <div class="auto-redirect">
        Автоматический переход через <span id="countdown">5</span> секунд...
    </div>
</div>

<script>
    // Автоматический редирект через 5 секунд
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');

    const countdown = setInterval(function() {
        seconds--;
        countdownElement.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(countdown);
            window.location.href = 'login.php';
        }
    }, 1000);
</script>
</body>
</html>