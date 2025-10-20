<?php
// new_ufmhrm/core/classes/User.php

class User
{
    private $_db;

    public function __construct($db)
    {
        $this->_db = $db;
    }

    /**
     * Attempts to log a user in by verifying their credentials.
     *
     * @param string $username The user's username.
     * @param string $password The user's plain-text password.
     * @return object|false The user object on successful login, or false on failure.
     */
    public function login($username, $password)
    {
        $stmt = $this->_db->query("SELECT * FROM users WHERE username = ?", [$username]);

        if ($stmt && $stmt->count()) {
            $user = $stmt->first();
            if (password_verify($password, $user->password)) {
                // --- KEY CHANGE ---
                // REMOVED: Session logic from this class.
                // CHANGED: Return the entire user object on success.
                return $user;
            }
        }
        // If anything fails (user not found, password incorrect), return false.
        return false;
    }

    /**
     * Note: This function is now redundant as we have is_admin_logged_in()
     * in helpers.php, which should be the single source of truth.
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['admin_id']);
    }
}

