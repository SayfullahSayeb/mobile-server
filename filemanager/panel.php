<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tinyfilemanager.php';
