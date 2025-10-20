<?php
// new_ufmhrm/auth/logout.php
require_once '../core/init.php';

// Use the helper function to handle the entire logout process.
logout_admin();

// Use the helper function to redirect back to the login page.
redirect('login.php');
exit();
