<?php
// new_ufmhrm/core/init.php

// MUST BE THE VERY FIRST THING. Starts the session on every page.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- CORE FILE LOADING ORDER ---

// 1. Load the configuration file which defines constants like database credentials and APP_URL.
require_once __DIR__ . '/config/config.php';

// 2. Include all global helper functions so they are available everywhere.
require_once __DIR__ . '/functions/helpers.php';

// 3. Set up a robust autoloader for all your class files.
spl_autoload_register(function ($class) {
    $class_file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($class_file)) {
        require_once $class_file;
    }
});

// 4. Create a single database instance for the application to use.
// This comes last so that all configs and classes are ready.
$db = Database::getInstance();

