<?php
// logout.php
require_once 'includes/config.php';

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>