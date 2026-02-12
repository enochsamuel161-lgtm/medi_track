<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: doctors.php");
    exit;
}

$id = (int) $_GET['id'];

// Fetch doctor details
$stmt = $pdo->prepare("
    SELECT d.*, dc.category_name, dc.id as category_id 
    FROM doctors d
    LEFT JOIN doctor_categories dc ON d.category_id = dc.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: doctors.php");
    exit;
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM doctor_categories ORDER BY category_name")->fetchAll();

// Update doctor
if (isset($_POST['update'])) {
    $fullname = $_POST['fullname'];
    $email    = $_POST['email'];
    $phone    = $_POST['phone'];
    $specialty = $_POST['specialty'];
    $category_id = $_POST['category_id'];
    $status   = $_POST['status'];

    $update = $pdo->prepare("
        UPDATE doctors 
        SET fullname = ?, email = ?, phone = ?, specialty = ?, category_id = ?, status = ?
        WHERE id = ?
    ");
    $update->execute([$fullname, $email, $phone, $specialty, $category_id, $status, $id]);

    header("Location: edit_doctor.php?id=$id&updated=1");
    exit;
}

// Add availability slot
if (isset($_POST['add_slot'])) {
    $slot_time = $_POST['slot_time'];

    $addSlot = $pdo->prepare("
        INSERT INTO doctor_slots (doctor_id, slot_time)
        VALUES (?, ?)
    ");
    $addSlot->execute([$id, $slot_time]);

    header("Location: edit_doctor.php?id=$id&slot_added=1");
    exit;
}

// Delete slot
if (isset($_GET['delete_slot'])) {
    $slot_id = (int) $_GET['delete_slot'];
    
    $checkSlot = $pdo->prepare("SELECT is_booked FROM doctor_slots WHERE id = ?");
    $checkSlot->execute([$slot_id]);
    $slot = $checkSlot->fetch();
    
    if ($slot && !$slot['is_booked']) {
        $deleteSlot = $pdo->prepare("DELETE FROM doctor_slots WHERE id = ?");
        $deleteSlot->execute([$slot_id]);
        header("Location: edit_doctor.php?id=$id&slot_deleted=1");
        exit;
    }
}

// Fetch slots
$slotStmt = $pdo->prepare("
    SELECT * FROM doctor_slots
    WHERE doctor_id = ?
    ORDER BY slot_time ASC
");
$slotStmt->execute([$id]);
$slots = $slotStmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin info for sidebar
$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_name = $stmt->fetchColumn();

// Get counts for sidebar badges
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
$pending_appointments_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM doctor_categories");
$total_categories_sidebar = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin | Edit Doctor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same style as other admin pages -->
    <aside class="lg:w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
        <div class="p-6">
            <div class="flex items-center justify-between lg:justify-start">
                <h2 class="text-2xl font-bold tracking-wide">
                    <span class="bg-white text-blue-800 px-2 py-1 rounded-lg">Medi</span>Track
                </h2>
                <!-- Mobile menu button -->
                <button id="mobileMenuBtn" class="lg:hidden text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
            
            <!-- Admin Info -->
            <div class="mt-6 flex items-center space-x-3 border-t border-blue-700 pt-4">
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                    <span class="text-xl font-semibold"><?= strtoupper(substr($admin_name ?? 'A', 0, 1)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($admin_name ?? 'Admin') ?></p>
                    <p class="text-xs text-blue-300 truncate">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav id="navMenu" class="mt-6 px-4 space-y-1 hidden lg:block">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="doctors.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
                <i class="fas fa-user-md w-5 h-5 mr-3"></i>
                <span>Doctors</span>
                <span class="ml-auto bg-blue-600 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_doctors_sidebar ?></span>
            </a>
            <a href="patients.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>Patients</span>
                <span class="ml-auto bg-white text-blue-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_patients_sidebar ?></span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if($pending_appointments_sidebar > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse"><?= $pending_appointments_sidebar ?></span>
                <?php endif; ?>
            </a>
            <a href="categories.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-tags w-5 h-5 mr-3"></i>
                <span>Categories</span>
                <span class="ml-auto bg-blue-600 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_categories_sidebar ?></span>
            </a>
            <div class="pt-6 mt-6 border-t border-blue-700">
                <a href="../../public/logout.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-red-600 rounded-lg transition-all">
                    <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 bg-gray-50 p-4 lg:p-8">
        
        <!-- Header with Breadcrumb -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <a href="doctors.php" class="hover:text-blue-600">Doctors</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Edit Doctor</span>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Edit Doctor</h1>
                    <p class="text-sm text-gray-600 mt-1">Update doctor information and manage availability</p>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-id-card mr-2"></i>ID: DOC-<?= str_pad($doctor['id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                    <a href="doctors.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Messages -->
        <?php if (isset($_GET['updated'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span>Doctor information updated successfully!</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['slot_added'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span>Availability slot added successfully!</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['slot_deleted'])): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-trash text-yellow-500 mr-3"></i>
                    <span>Availability slot deleted successfully!</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-yellow-700 hover:text-yellow-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Main Grid - 2 columns on desktop -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- LEFT COLUMN - Edit Doctor Form -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold text-white">Doctor Information</h2>
                            <p class="text-blue-100 text-sm">Edit personal and professional details</p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="p-6">
                    <div class="space-y-4">
                        <!-- Doctor Avatar/Status Summary -->
                        <div class="flex items-center p-4 bg-gray-50 rounded-lg mb-2">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-2xl">
                                <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Current Status</p>
                                <div class="flex items-center mt-1">
                                    <?php if ($doctor['status'] === 'available'): ?>
                                        <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                        <span class="font-medium text-green-700">Available</span>
                                    <?php else: ?>
                                        <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                                        <span class="font-medium text-red-700">Unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-blue-500 mr-2"></i>Full Name
                            </label>
                            <input type="text" name="fullname" required
                                value="<?= htmlspecialchars($doctor['fullname']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-blue-500 mr-2"></i>Email Address
                            </label>
                            <input type="email" name="email" required
                                value="<?= htmlspecialchars($doctor['email']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-blue-500 mr-2"></i>Phone Number
                            </label>
                            <input type="text" name="phone"
                                value="<?= htmlspecialchars($doctor['phone']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                        </div>

                        <!-- Specialty -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-stethoscope text-blue-500 mr-2"></i>Specialty
                            </label>
                            <input type="text" name="specialty"
                                value="<?= htmlspecialchars($doctor['specialty'] ?? '') ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tags text-blue-500 mr-2"></i>Category
                            </label>
                            <div class="relative">
                                <select name="category_id" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white appearance-none">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $doctor['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-500"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-toggle-on text-blue-500 mr-2"></i>Status
                            </label>
                            <div class="relative">
                                <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white appearance-none">
                                    <option value="available" <?= $doctor['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                    <option value="unavailable" <?= $doctor['status'] === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                                    <option value="busy" <?= $doctor['status'] === 'busy' ? 'selected' : '' ?>>Busy</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-500"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="update"
                            class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold px-6 py-3 rounded-lg transition-all transform hover:scale-105 flex items-center justify-center">
                            <i class="fas fa-save mr-2"></i>
                            Update Doctor Information
                        </button>
                    </div>
                </form>
            </div>

            <!-- RIGHT COLUMN - Availability Management -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold text-white">Availability Slots</h2>
                            <p class="text-green-100 text-sm">Manage doctor's available time slots</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Add Slot Form -->
                    <form method="POST" class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-plus-circle text-green-500 mr-2"></i>Add New Time Slot
                        </label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input type="datetime-local" name="slot_time" required
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition bg-gray-50 focus:bg-white">
                            <button type="submit" name="add_slot"
                                class="sm:w-auto bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold px-6 py-3 rounded-lg transition-all flex items-center justify-center">
                                <i class="fas fa-plus mr-2"></i>
                                Add Slot
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Set date and time for doctor's availability
                        </p>
                    </form>

                    <!-- Slots List -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-list mr-2 text-green-600"></i>
                                Current Slots
                            </h3>
                            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                <?= count($slots) ?> slots
                            </span>
                        </div>

                        <?php if (empty($slots)): ?>
                            <div class="text-center py-8 bg-gray-50 rounded-lg">
                                <i class="fas fa-calendar-times text-gray-400 text-4xl mb-3"></i>
                                <p class="text-gray-500">No availability slots added yet.</p>
                                <p class="text-gray-400 text-sm mt-1">Use the form above to add time slots</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                                <?php 
                                // Separate slots into upcoming and past
                                $now = new DateTime();
                                $upcoming_slots = [];
                                $past_slots = [];
                                
                                foreach ($slots as $slot) {
                                    $slot_time = new DateTime($slot['slot_time']);
                                    if ($slot_time > $now) {
                                        $upcoming_slots[] = $slot;
                                    } else {
                                        $past_slots[] = $slot;
                                    }
                                }
                                ?>

                                <?php if (!empty($upcoming_slots)): ?>
                                    <h4 class="text-sm font-medium text-green-600 mb-2">Upcoming Slots</h4>
                                    <?php foreach ($upcoming_slots as $slot): ?>
                                        <div class="flex items-center justify-between p-3 bg-green-50 border border-green-100 rounded-lg hover:shadow-md transition-shadow">
                                            <div class="flex items-start">
                                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-calendar-check text-green-600"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="font-medium text-gray-800">
                                                        <?= date("D, M d, Y", strtotime($slot['slot_time'])) ?>
                                                    </p>
                                                    <p class="text-sm text-gray-600">
                                                        <?= date("h:i A", strtotime($slot['slot_time'])) ?>
                                                    </p>
                                                    <span class="text-xs <?= $slot['is_booked'] ? 'text-orange-600' : 'text-green-600' ?>">
                                                        <?= $slot['is_booked'] ? '✓ Booked' : '○ Available' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if (!$slot['is_booked']): ?>
                                                <a href="?id=<?= $id ?>&delete_slot=<?= $slot['id'] ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this slot?')"
                                                   class="text-red-600 hover:text-red-800 p-2 hover:bg-red-50 rounded-lg transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 bg-gray-100 px-3 py-1 rounded-full">
                                                    Booked
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($past_slots)): ?>
                                    <h4 class="text-sm font-medium text-gray-500 mt-4 mb-2">Past Slots</h4>
                                    <?php foreach ($past_slots as $slot): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg opacity-75">
                                            <div class="flex items-start">
                                                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-calendar-check text-gray-500"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="font-medium text-gray-600">
                                                        <?= date("D, M d, Y", strtotime($slot['slot_time'])) ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        <?= date("h:i A", strtotime($slot['slot_time'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="text-xs text-gray-500">
                                                <?= $slot['is_booked'] ? 'Completed' : 'Expired' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-calendar-check text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-xs text-gray-500">Total Slots</p>
                    <p class="text-lg font-bold text-gray-800"><?= count($slots) ?></p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-xs text-gray-500">Available</p>
                    <p class="text-lg font-bold text-green-600">
                        <?= count(array_filter($slots, fn($s) => !$s['is_booked'])) ?>
                    </p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bookmark text-orange-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-xs text-gray-500">Booked</p>
                    <p class="text-lg font-bold text-orange-600">
                        <?= count(array_filter($slots, fn($s) => $s['is_booked'])) ?>
                    </p>
                </div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-arrow-up text-purple-600"></i>
                </div>
                <div class="ml-3">
                    <p class="text-xs text-gray-500">Upcoming</p>
                    <p class="text-lg font-bold text-purple-600"><?= count($upcoming_slots ?? []) ?></p>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript for Mobile Menu -->
<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 1024) {
            const isClickInsideNav = navMenu?.contains(event.target);
            const isClickOnButton = mobileMenuBtn?.contains(event.target);
            
            if (!isClickInsideNav && !isClickOnButton && !navMenu?.classList.contains('hidden')) {
                navMenu.classList.add('hidden');
            }
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.bg-green-100, .bg-yellow-100');
        alerts.forEach(alert => {
            if (alert.classList.contains('border-l-4')) {
                alert.remove();
            }
        });
    }, 5000);
</script>

</body>
</html>