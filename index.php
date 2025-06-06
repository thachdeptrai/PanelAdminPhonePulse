<?php
// Include configuration and functions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if the user is logged in
if (isLoggedIn()) {
    // Redirect to dashboard if logged in
    redirect('pages/dashboard.php');
} else {
    // Redirect to login page if not logged in
    redirect('login.php');
}