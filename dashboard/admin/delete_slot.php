<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit;
}

$id = $_GET['id'];
$doctor_id = $_GET['doctor_id'];

$stmt = $pdo->prepare("
    DELETE FROM doctor_slots 
    WHERE id = ? AND is_booked = 0
");
$stmt->execute([$id]);

header("Location: edit_doctor.php?id=$doctor_id");
exit;
