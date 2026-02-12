<?php
session_start();
require "../../config/database.php";

// Check if patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

// Get doctor ID from GET
$doctor_id = $_GET['id'] ?? null;
if (!$doctor_id) {
    header("Location: appointments.php");
    exit;
}

// Fetch doctor details
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: appointments.php");
    exit;
}

// Fetch available slots
$slot_stmt = $pdo->prepare("
    SELECT * FROM doctor_slots 
    WHERE doctor_id = ? AND is_booked = 0
    ORDER BY slot_time ASC
");
$slot_stmt->execute([$doctor_id]);
$slots = $slot_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Doctor Details</title>
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

  <!-- MAIN -->
  <main class="flex-1 p-8">

    <div class="bg-white p-6 rounded-lg shadow max-w-xl">

      <h1 class="text-2xl font-bold mb-4">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h1>
      <p class="text-gray-700 mb-2"><strong>Specialty:</strong> <?= htmlspecialchars($doctor['specialty']) ?></p>
      <p class="text-gray-700 mb-2"><strong>Email:</strong> <?= htmlspecialchars($doctor['email']) ?></p>
      <p class="mb-4">
        <strong>Status:</strong>
        <?php if ($doctor['status'] === 'available'): ?>
          <span class="text-green-600 font-semibold">Available</span>
        <?php else: ?>
          <span class="text-red-600 font-semibold">Unavailable</span>
        <?php endif; ?>
      </p>

      <!-- Success / Error messages -->
      <?php if (isset($_GET['success'])): ?>
        <p class="text-green-600 font-semibold mb-4">Appointment booked successfully!</p>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <p class="text-red-600 font-semibold mb-4">
          Sorry, this slot was already booked. Please choose another.
        </p>
      <?php endif; ?>

      <!-- Available slots -->
      <?php if ($doctor['status'] === 'available' && !empty($slots)): ?>
        <h2 class="text-xl font-semibold mb-2">Available Slots</h2>
        <?php foreach ($slots as $slot): ?>
          <form action="book_appointment.php" method="POST" class="mb-2">
            <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
            <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 w-full text-left">
              <?= date("D, M d, Y H:i", strtotime($slot['slot_time'])) ?>
            </button>
          </form>
        <?php endforeach; ?>
      <?php elseif ($doctor['status'] === 'available'): ?>
        <p class="text-gray-500">No available slots at the moment.</p>
      <?php else: ?>
        <p class="text-gray-500">Doctor is currently unavailable.</p>
      <?php endif; ?>

    </div>

  </main>

</div>

</body>
</html>
