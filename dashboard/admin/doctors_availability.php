<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// fetch all doctors
$doctors = $pdo->query("SELECT id, fullname FROM doctors")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = $_POST['doctor_id'];
    $slot_time = $_POST['slot_time'];

    if ($doctor_id && $slot_time) {
        $stmt = $pdo->prepare("INSERT INTO doctor_slots (doctor_id, slot_time) VALUES (?, ?)");
        $stmt->execute([$doctor_id, $slot_time]);
        $success = "Slot added successfully!";
    } else {
        $error = "Please select doctor and time.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Add Doctor Slot</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

<h1 class="text-2xl font-bold mb-6">Add Doctor Slot</h1>

<?php if(isset($success)) echo "<p class='text-green-600 mb-4'>$success</p>"; ?>
<?php if(isset($error)) echo "<p class='text-red-600 mb-4'>$error</p>"; ?>

<form method="POST" class="bg-white p-6 rounded shadow max-w-lg">
    <select name="doctor_id" required class="w-full border p-2 mb-4">
        <option value="">Select Doctor</option>
        <?php foreach($doctors as $doc): ?>
            <option value="<?= $doc['id']; ?>"><?= htmlspecialchars($doc['fullname']); ?></option>
        <?php endforeach; ?>
    </select>

    <input type="datetime-local" name="slot_time" required class="w-full border p-2 mb-4">

    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Slot</button>
</form>

</body>
</html>
