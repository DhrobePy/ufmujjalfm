<?php
// new_ufmhrm/auth/login_handler.php

require_once '../core/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- STEP 1: CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_message('Invalid or missing security token.', 'error');
        redirect('login.php');
        exit();
    }
    
    // The token is valid, so we can remove it to prevent it from being used again.
    unset($_SESSION['csrf_token']);

    // --- STEP 2: User Authentication ---
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Create a new User object and attempt to log in.
    $user_obj = new User($db);
    $loggedInUser = $user_obj->login($username, $password);

    // --- STEP 3: Handle Login Result ---
    if ($loggedInUser) {
        // If login() returns a user object, it was successful.
        // Use the helper functions to set the session and redirect.
        login_admin((array)$loggedInUser);
        set_message('Welcome back, ' . $loggedInUser->full_name . '!', 'success');
        redirect_to_dashboard();
        exit();

    } else {
        // If login() returns false, the credentials were wrong.
        set_message('Invalid credentials. Please try again.', 'error');
        redirect('login.php');
        exit();
    }

} else {
    // Redirect if someone tries to access this page directly
    redirect('login.php');
    exit();
}

