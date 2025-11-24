<?php
// new_ufmhrm/core/functions/helpers.php

// --- CORE LOGIN & SESSION HELPERS ---

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

function isLoggedIn() {
    return is_admin_logged_in();
}

/**
 * (MODIFIED) Logs in a user by setting all necessary session variables,
 * INCLUDING branch_id.
 * @param array $user - The user data from the database.
 */
function login_admin($user) {
    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_name'] = $user['full_name'] ?? $user['username'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['admin_branch_id'] = $user['branch_id']; // <-- NEWLY ADDED
    session_regenerate_id(true);
}

function logout_admin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_branch_id']); // <-- NEWLY ADDED
    set_message('You have been successfully logged out.', 'success');
    session_destroy();
}

/**
 * (MODIFIED) Gets the current logged-in user's details from the session,
 * INCLUDING branch_id.
 * @return array|null
 */
function getCurrentUser() {
    if (is_admin_logged_in()) {
        return [
            'id'        => $_SESSION['admin_id'],
            'full_name' => $_SESSION['admin_name'] ?? 'Admin',
            'role'      => $_SESSION['admin_role'] ?? 'admin',
            'branch_id' => $_SESSION['admin_branch_id'] ?? null, // <-- NEWLY ADDED
        ];
    }
    return null;
}

/**
 * (No changes here, already correct)
 * Redirects the user to the appropriate dashboard based on their role.
 */
function redirect_to_dashboard() {
    $role = $_SESSION['admin_role'] ?? 'employee';
    $path = '../employee/index.php';

    switch ($role) {
        case 'superadmin':
        case 'Admin':
        case 'Admin-HO':
        case 'Admin-srg':
        case 'Admin-rampura':
            $path = '../admin/index.php';
            break;
        
        case 'Accounts':
        case 'Accounts-HO':
        case 'Accounts- Srg':
        case 'Accounts- Rampura':
            $path = '../accounts/index.php';
            break;
        
        case 'Employee':
            $path = '../employee/index.php';
            break;
    }
    redirect($path);
}

// --- UTILITY & ASSET HELPERS ---

function url($path = '') {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function asset($path) {
    return rtrim(APP_URL, '/') . '/assets/' . ltrim($path, '/');
}

function redirect($location) {
    header("Location: " . $location);
    exit();
}

function sanitize($dirty){
    return htmlentities($dirty, ENT_QUOTES, 'UTF-8');
}

// --- MESSAGE DISPLAY HELPERS ---

function set_message($message, $type = 'success') {
    $session_key = ($type === 'error') ? 'error_flash' : 'success_flash';
    $_SESSION[$session_key] = $message;
}

function display_message(){
    $message = '';
    if(isset($_SESSION['success_flash'])){
        $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-r-lg" role="alert">
                      <p class="font-bold">Success</p>
                      <p>' . $_SESSION['success_flash'] . '</p>
                    </div>';
        unset($_SESSION['success_flash']);
    }

    if(isset($_SESSION['error_flash'])){
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-r-lg" role="alert">
                      <p class="font-bold">Error</p>
                      <p>' . $_SESSION['error_flash'] . '</p>
                    </div>';
        unset($_SESSION['error_flash']);
    }
    return $message;
}