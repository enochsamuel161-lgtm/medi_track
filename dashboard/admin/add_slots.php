<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch doctors
$doctors = $pdo->query("SELECT id, fullname FROM doctors WHERE status = 'available'")
               ->fetchAll(PDO::FETCH_ASSOC);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $date = $_POST['date'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $interval = (int) $_POST['interval']; // minutes

    $startTime = strtotime("$date $start");
    $endTime   = strtotime("$date $end");

    $pdo->beginTransaction();
    try {
        while ($startTime < $endTime) {
            $slotTime = date("Y-m-d H:i:s", $startTime);

            $stmt = $pdo->prepare("
                INSERT INTO doctor_slots (doctor_id, slot_time)
                VALUES (?, ?)
            ");
            $stmt->execute([$doctor_id, $slotTime]);

            $startTime += $interval * 60;
        }

        $pdo->commit();
        $message = "Slots added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error adding slots";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Doctor Availability</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

<div class="max-w-xl mx-auto bg-white p-6 rounded-lg shadow">

<h1 class="text-2xl font-bold mb-4">Add Doctor Availability</h1>

<?php if ($message): ?>
<p class="mb-4 text-green-600 font-semibold"><?= $message ?></p>
<?php endif; ?>

<form method="POST" class="space-y-4">

<select name="doctor_id" required class="w-full border p-2 rounded">
    <option value="">Select Doctor</option>
    <?php foreach ($doctors as $doc): ?>
        <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['fullname']) ?></option>
    <?php endforeach; ?>
</select>

<input type="date" name="date" required class="w-full border p-2 rounded">

<div class="flex gap-4">
    <input type="time" name="start_time" required class="w-full border p-2 rounded">
    <input type="time" name="end_time" required class="w-full border p-2 rounded">
</div>

<input type="number" name="interval" value="30" min="5"
       class="w-full border p-2 rounded"
       placeholder="Interval in minutes">

<button class="bg-blue-600 text-white px-4 py-2 rounded w-full">
    Add Slots
</button>

</form>

</div>
</body>
</html>
