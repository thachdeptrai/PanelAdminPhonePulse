<?php
require_once 'includes/functions.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: dang_nhap');
    exit;
} else {
    header('Location: trang_chu');
    exit;
}
