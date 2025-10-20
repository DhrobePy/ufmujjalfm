<?php
// new_ufmhrm/index.php

// *** THIS LINE IS NOW FIXED ***
// It correctly points to the 'core' folder in the same directory.
require_once 'core/init.php';

// Redirect logic remains the same
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/index.php');
    exit();
} elseif (isset($_SESSION['employee_id'])) {
    header('Location: employee/index.php');
    exit();
} else {
    header('Location: auth/login.php');
    exit();
}