<?php
session_start();
require "../config/database.php";

// allow only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        header("Location: categories.php?error=empty");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO doctor_categories (name) VALUES (?)");
    $stmt->execute([$name]);

    header("Location: categories.php?success=1");
    exit;
}

header("Location: categories.php");
exit;
