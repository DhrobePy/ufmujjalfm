<?php
// new_ufmhrm/auth/login_handler.php

// This file now gets the $db variable from init.php
require_once '../core/init.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Pass the globally available $db object to the User class
    $user = new User($db); 
    $login = $user->login($username, $password);

    if ($login) {
        // Success: Set the session and redirect to the admin dashboard
        $_SESSION['success_flash'] = 'Login successful!';
        header('Location: ../admin/index.php');
        exit();
    } else {
        // Failure: Set an error and redirect back to the login page
        $_SESSION['error_flash'] = 'Invalid credentials. Please try again.';
        header('Location: login.php');
        exit();
    }
} else {
    // Redirect if someone tries to access this page directly
    header('Location: login.php');
    exit();
}