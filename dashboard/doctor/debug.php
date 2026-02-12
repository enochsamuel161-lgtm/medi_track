<?php
session_start();
require "../../config/database.php";

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die("You must log in as a doctor to view this page.");
}

echo "<h2>Doctor Session Info</h2>";
echo "Logged-in doctor session ID: " . $_SESSION['user_id'] . "<br>";
echo "Doctor fullname: " . ($_SESSION['doctor_name'] ?? 'N/A') . "<br>";

// Show appointments table
echo "<h2>Appointments Table</h2>";
$appts = $pdo->query("SELECT * FROM appointments")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($appts);
echo "</pre>";

// Show doctors table
echo "<h2>Doctors Table</h2>";
$docs = $pdo->query("SELECT * FROM doctors")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($docs);
echo "</pre>";

// Show doctor_slots table
echo "<h2>Doctor Slots Table</h2>";
$slots = $pdo->query("SELECT * FROM doctor_slots")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($slots);
echo "</pre>";
