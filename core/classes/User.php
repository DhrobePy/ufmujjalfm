<?php
// new_ufmhrm/core/classes/User.php

class User
{
    private $_db;

    // The constructor now correctly expects the database object
    public function __construct($db)
    {
        $this->_db = $db;
    }

    public function login($username, $password)
    {
        // The rest of the logic remains the same, using the passed-in connection
        $stmt = $this->_db->query("SELECT * FROM users WHERE username = ?", [$username]);

        if ($stmt && $stmt->count()) {
            $user = $stmt->first();
            if (password_verify($password, $user->password)) {
                // Set session variables
                $_SESSION['admin_id'] = $user->id;
                $_SESSION['admin_name'] = $user->full_name ?? $user->username;
                $_SESSION['admin_role'] = $user->role;
                return true;
            }
        }
        return false;
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['admin_id']);
    }
}