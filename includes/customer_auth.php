<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function require_customer(): void
{
    if (empty($_SESSION['customer_id'])) {
        header('Location: login.php');
        exit;
    }
}