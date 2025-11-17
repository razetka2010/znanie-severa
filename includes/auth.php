<?php
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

function isSchoolAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'school_admin';
}

function isTeacher() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'teacher' || $_SESSION['user_role'] === 'class_teacher');
}

function hasPermission($permission) {
    // В реальном проекте проверяем права из БД
    $super_admin_permissions = ['manage_schools', 'manage_admins', 'view_all_users', 'manage_curriculum', 'manage_roles', 'reset_passwords', 'system_settings', 'academic_periods', 'global_reports'];

    if (isSuperAdmin() && in_array($permission, $super_admin_permissions)) {
        return true;
    }

    return false;
}

function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'login' => $_SESSION['user_login'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
        'name' => $_SESSION['user_name'] ?? null
    ];
}
?>
