<?php
session_start();
require "../../config/database.php";

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form data
    $fullname    = $_POST['fullname'] ?? '';
    $email       = $_POST['email'] ?? '';
    $phone       = $_POST['phone'] ?? '';
    $specialty   = $_POST['specialty'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $password    = $_POST['password'] ?? '';

    // Basic validation
    if ($fullname && $email && $phone && $specialty && $category_id && $password) {

        // Ensure category_id is an integer
        $category_id = (int) $category_id;

        // Check if email already exists in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            echo "<p class='text-red-500'>Email already exists. Please use another email.</p>";
            exit;
        }

        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // --- Insert login info into users table ---
        $stmt1 = $pdo->prepare("
            INSERT INTO users (fullname, email, password, role)
            VALUES (?, ?, ?, 'doctor')
        ");
        $stmt1->execute([$fullname, $email, $password_hash]);

        // --- Insert profile info into doctors table ---
        $stmt2 = $pdo->prepare("
            INSERT INTO doctors (fullname, email, phone, specialty, category_id, status)
            VALUES (?, ?, ?, ?, ?, 'available')
        ");
        $stmt2->execute([$fullname, $email, $phone, $specialty, $category_id]);

        // Redirect to doctors list with success message
        header("Location: doctors.php?success=Doctor added successfully");
        exit;

    } else {
        echo "<p class='text-red-500'>Please fill all fields.</p>";
    }

} else {
    // Prevent direct access
    header("Location: add_doctor.php");
    exit;
}
?>
