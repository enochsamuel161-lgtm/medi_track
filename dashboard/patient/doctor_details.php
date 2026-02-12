<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

$doctor_id = $_GET['id'] ?? null;
if (!$doctor_id) {
    header("Location: appointments.php");
    exit;
}

// fetch doctor info
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();
if (!$doctor) {
    header("Location: appointments.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Doctor Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

<div class="max-w-xl mx-auto bg-white p-6 rounded-lg shadow">
    <h1 class="text-2xl font-bold mb-4">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h1>
    <p><strong>Specialty:</strong> <?= htmlspecialchars($doctor['specialty']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($doctor['email']) ?></p>
    <p><strong>Status:</strong> <?= $doctor['status'] === 'available' ? 'Available' : 'Unavailable' ?></p>

    <?php if (isset($_GET['success'])): ?>
        <p class="text-green-600 font-semibold mt-4">Appointment booked successfully!</p>
           <a href="appointments.php" class="block mb-3 text-blue-600 font-semibold">Back</a>
    <?php endif; ?>
</div>

</body>
</html>
