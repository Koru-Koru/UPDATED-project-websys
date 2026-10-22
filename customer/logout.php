<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['customer_id'], $_SESSION['customer_name']);
set_flash('You have been signed out.', 'success');
header('Location: login.php'); exit;
