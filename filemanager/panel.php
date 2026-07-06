<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: ../index.php');
    exit;
}
$use_auth = false;
require_once __DIR__ . '/tinyfilemanager.php';
