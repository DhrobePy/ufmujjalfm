<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f0f9ff', 100:'#e0f2fe', 200:'#bae6fd', 300:'#7dd3fc', 400:'#38bdf8', 500:'#0ea5e9', 600:'#0284c7', 700:'#0369a1', 800:'#075985', 900:'#0c4a6e' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex flex-col">
    
    <?php if (isLoggedIn()): ?>
    <nav class="bg-white shadow-lg border-b border-gray-200" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="<?php echo url('accounts/index.php'); ?>" class="flex items-center">
                            <i class="fas fa-users text-primary-600 text-2xl mr-2"></i>
                            <span class="font-bold text-xl text-gray-900">UFM-HRM (Accounts)</span>
                        </a>
                    </div>
                    
                    <div class="hidden md:ml-6 md:flex md:space-x-8">
                        <a href="<?php echo url('accounts/index.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Dashboard</a>
                        <a href="<?php echo url('accounts/employees.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Employees</a>
                        <a href="<?php echo url('accounts/attendance.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Attendance</a>
                        <a href="<?php echo url('accounts/leaves.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Leaves</a>
                        <a href="<?php echo url('accounts/payroll.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Payroll</a>
                        <a href="<?php echo url('accounts/loans.php'); ?>" class="border-transparent text-gray-500 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">Loans</a>
                    </div>
                </div>
                
                <div class="hidden md:ml-6 md:flex md:items-center">
                    <div class="ml-3 relative" x-data="{ open: false }">
                        <div>
                            <button @click="open = !open" class="bg-white flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center">
                                    <span class="text-white font-medium text-sm"><?php echo strtoupper(substr(getCurrentUser()['full_name'], 0, 1)); ?></span>
                                </div>
                            </button>
                        </div>
                        <div x-show="open" @click.away="open = false" x-transition class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                            <div class="py-1">
                                <a href="<?php echo url('auth/logout.php'); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="md:hidden flex items-center">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div x-show="mobileMenuOpen" class="md:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <a href="<?php echo url('accounts/index.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Dashboard</a>
                <a href="<?php echo url('accounts/employees.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Employees</a>
                <a href="<?php echo url('accounts/attendance.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Attendance</a>
                <a href="<?php echo url('accounts/leaves.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Leaves</a>
                <a href="<?php echo url('accounts/payroll.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Payroll</a>
                <a href="<?php echo url('accounts/loans.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Loans</a>
                <a href="<?php echo url('auth/logout.php'); ?>" class="block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Sign Out</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="py-6 flex-grow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <?php echo display_message(); ?>