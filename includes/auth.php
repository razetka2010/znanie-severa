<?php
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !isset($_SESSION['user_school_id'])) {
        return false;
    }

    // Проверяем время последней активности (8 часов)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 28800)) {
        session_unset();
        session_destroy();
        return false;
    }

    // Обновляем время активности
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAuth() {
    if (!checkAuth()) {
        header('Location: ../login.php');
        exit;
    }
}

function requireSuperAdmin() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'super_admin') {
        header('Location: ../unauthorized.php');
        exit;
    }
}

function requireSchoolAdmin() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'school_admin') {
        header('Location: ../unauthorized.php');
        exit;
    }
}

function requireTeacher() {
    requireAuth();
    $allowed_roles = ['teacher', 'class_teacher'];
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        header('Location: ../unauthorized.php');
        exit;
    }
}

function requireStudent() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'student') {
        header('Location: ../unauthorized.php');
        exit;
    }
}

function requireParent() {
    requireAuth();
    if ($_SESSION['user_role'] !== 'parent') {
        header('Location: ../unauthorized.php');
        exit;
    }
}

// Функция для получения информации о текущем пользователе
function getCurrentUser() {
    if (!checkAuth()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'login' => $_SESSION['user_login'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
        'school_id' => $_SESSION['user_school_id'],
        'school_name' => $_SESSION['school_name'] ?? ''
    ];
}

// Функция для проверки прав доступа к школе
function hasAccessToSchool($school_id) {
    $user = getCurrentUser();
    if (!$user) return false;

    // Суперадмин имеет доступ ко всем школам
    if ($user['role'] === 'super_admin') {
        return true;
    }

    // Остальные пользователи имеют доступ только к своей школе
    return $user['school_id'] == $school_id;
}

// Функция для выхода
function logout() {
    session_unset();
    session_destroy();
    header('Location: ../login.php');
    exit;
}
?>
