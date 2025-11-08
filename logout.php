<?php
require_once __DIR__ . '\inc\functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlash('error', 'Invalid CSRF token');
    redirect('/saving_ant/dashboard.php');
}

// Destroy session
session_destroy();

// Set flash message before destroying session
setFlash('success', 'You have been successfully logged out.');

// Redirect to login page
setFlash('success', 'You have been successfully logged out.');
redirect('/saving_ant/index.php');