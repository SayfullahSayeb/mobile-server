<?php
session_start();
if (empty($_SESSION['authenticated'])) {
    header('Location: ../control.php');
    exit;
}
require_once __DIR__ . '/tinyfilemanager.php';
