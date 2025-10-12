<?php
// new_ufmhrm/core/functions/helpers.php

// --- CORE LOGIN & SESSION HELPERS ---
function is_admin_logged_in(){
    return isset($_SESSION['admin_id']);
}

function isLoggedIn() {
    return is_admin_logged_in();
}

function getCurrentUser() {
    if (is_admin_logged_in()) {
        return [
            'full_name' => $_SESSION['admin_name'] ?? 'Admin',
            'role'      => $_SESSION['admin_role'] ?? 'admin',
        ];
    }
    return null;
}

// --- URL & ASSET HELPERS (CORRECTED) ---
// These now use APP_URL from your config file.
function url($path = '') {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function asset($path) {
    return rtrim(APP_URL, '/') . '/assets/' . ltrim($path, '/');
}

// --- MESSAGE DISPLAY HELPER ---
function display_message(){
    $message = '';
    if(isset($_SESSION['success_flash'])){
        $message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                      <p class="font-bold">Success</p>
                      <p>' . $_SESSION['success_flash'] . '</p>
                    </div>';
        unset($_SESSION['success_flash']);
    }

    if(isset($_SESSION['error_flash'])){
        $message = '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                      <p class="font-bold">Error</p>
                      <p>' . $_SESSION['error_flash'] . '</p>
                    </div>';
        unset($_SESSION['error_flash']);
    }
    return $message;
}