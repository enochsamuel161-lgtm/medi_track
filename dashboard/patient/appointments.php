<?php
session_start();
require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch doctor categories
$stmt = $pdo->query("SELECT * FROM doctor_categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-white shadow p-6">
        <h2 class="text-xl font-bold text-blue-600 mb-6">Patient Panel</h2>
        <a href="index.php" class="block mb-3 text-gray-600">Dashboard</a>
        <a href="appointments.php" class="block mb-3 text-blue-600 font-semibold">Appointments</a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-8">
        <h1 class="text-2xl font-bold mb-6">Choose Doctor Category</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($categories as $cat): ?>
                <a href="book_appointment.php?category=<?= urlencode($cat['category_name']) ?>"
                   class="block bg-white p-6 rounded-xl shadow hover:shadow-lg hover:bg-blue-50 transition">
                    <h3 class="text-lg font-semibold text-blue-600">
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </h3>
                    <p class="text-gray-500 mt-2">View available doctors</p>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

</div>

</body>
</html>
