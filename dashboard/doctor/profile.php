<?php
session_start();
require "../../config/database.php";

// Only allow logged-in doctors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../../public/login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];

// Fetch complete doctor profile
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.fullname,
        d.email,
        d.phone,
        d.specialty,
        d.status,
        d.category_id,
        d.created_at,
        dc.category_name,
        u.date_of_birth,
        u.blood_group,
        u.address,
        u.city,
        u.state,
        u.zip_code,
        u.profile_picture
    FROM doctors d
    LEFT JOIN doctor_categories dc ON d.category_id = dc.id
    LEFT JOIN users u ON d.email = u.email
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: ../../public/logout.php");
    exit;
}

// Update session with latest data
$_SESSION['fullname'] = $doctor['fullname'];
$_SESSION['specialty'] = $doctor['specialty'] ?? 'General';
$_SESSION['email'] = $doctor['email'] ?? '';
$_SESSION['phone'] = $doctor['phone'] ?? '';

// Get statistics for profile
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.patient_id) as total_patients,
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN ds.slot_time >= NOW() AND a.status != 'cancelled' THEN 1 END) as upcoming,
        COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ?
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch();

// Get available slots count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM doctor_slots 
    WHERE doctor_id = ? AND is_booked = 0 AND slot_time > NOW()
");
$stmt->execute([$doctor_id]);
$available_slots = $stmt->fetchColumn();

// Get all categories for dropdown
$categories = $pdo->query("SELECT * FROM doctor_categories ORDER BY category_name")->fetchAll();

// Get initials for avatar
$name_parts = explode(' ', $doctor['fullname']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($doctor['fullname'], 0, 2));
}

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'] ?? '';
    $specialty = $_POST['specialty'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $status = $_POST['status'] ?? 'available';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $blood_group = $_POST['blood_group'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Update doctors table
        $stmt = $pdo->prepare("
            UPDATE doctors SET
                fullname = ?,
                phone = ?,
                specialty = ?,
                category_id = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullname, $phone, $specialty, $category_id, $status, $doctor_id]);
        
        // Update users table (if record exists)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$doctor['email']]);
        
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                UPDATE users SET
                    fullname = ?,
                    phone = ?,
                    address = ?,
                    city = ?,
                    state = ?,
                    zip_code = ?,
                    date_of_birth = ?,
                    blood_group = ?
                WHERE email = ?
            ");
            $stmt->execute([$fullname, $phone, $address, $city, $state, $zip_code, $date_of_birth, $blood_group, $doctor['email']]);
        }
        
        $pdo->commit();
        
        // Update session
        $_SESSION['fullname'] = $fullname;
        $_SESSION['specialty'] = $specialty;
        $_SESSION['phone'] = $phone;
        
        $success_message = "Profile updated successfully!";
        
        // Refresh doctor data
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Get pending appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ? AND ds.slot_time >= NOW() AND a.status = 'pending'
");
$stmt->execute([$doctor_id]);
$pending_count = $stmt->fetchColumn();

// Get unread messages count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE doctor_id = ? AND status = 'sent'
");
$stmt->execute([$doctor_id]);
$unread_messages = $stmt->fetchColumn();

// Blood groups array
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Doctor status options
$status_options = ['available', 'busy', 'unavailable'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Doctor Profile | MediTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        .profile-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .edit-mode {
            border: 2px solid #3b82f6;
            background-color: #f0f9ff;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Enhanced doctor sidebar -->
    <aside class="lg:w-80 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
        <!-- Doctor Profile Section -->
        <div class="p-6">
            <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-lg">
                    <span class="text-3xl font-bold text-blue-800"><?= htmlspecialchars($initials) ?></span>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold truncate">Dr. <?= htmlspecialchars($doctor['fullname']) ?></h2>
                    <p class="text-sm text-blue-200 flex items-center mt-1">
                        <i class="fas fa-stethoscope mr-2 text-xs"></i>
                        <?= htmlspecialchars($doctor['specialty'] ?? 'General') ?>
                    </p>
                    <p class="text-xs text-blue-300 mt-2 flex items-center">
                        <i class="fas fa-envelope mr-2"></i>
                        <?= htmlspecialchars($doctor['email'] ?? 'Not provided') ?>
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-2 gap-3 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $stats['total_patients'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Total Patients</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $available_slots ?></p>
                    <p class="text-xs text-blue-200">Available Slots</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="pending_appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-hourglass-half w-5 h-5 mr-3"></i>
                <span>Pending Reviews</span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="messages.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-comments w-5 h-5 mr-3"></i>
                <span>Patient Messages</span>
                <?php if ($unread_messages > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $unread_messages ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="manage_slots.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-clock w-5 h-5 mr-3"></i>
                <span>My Schedule</span>
            </a>
            <a href="patients.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>My Patients</span>
            </a>
            <a href="profile.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
                <i class="fas fa-user-circle w-5 h-5 mr-3"></i>
                <span>Profile</span>
            </a>
            
            <div class="pt-6 mt-6 border-t border-blue-700">
                <a href="../../public/logout.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-red-600 rounded-xl transition-all">
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
                        <span class="text-gray-700 font-medium">My Profile</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Doctor Profile
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Manage your professional information and availability
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <button onclick="toggleEditMode()" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-edit mr-2"></i>
                        <span id="editButtonText">Edit Profile</span>
                    </button>
                    <a href="manage_slots.php" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-clock mr-2"></i>
                        Manage Schedule
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between animate-pulse">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Profile Content -->
        <form method="POST" id="profileForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column - Profile Picture & Basic Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-md p-6 text-center">
                        <!-- Profile Avatar Large -->
                        <div class="relative inline-block">
                            <div class="w-32 h-32 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-4xl mx-auto shadow-lg">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <div class="absolute bottom-0 right-0 bg-green-500 w-6 h-6 rounded-full border-4 border-white"></div>
                        </div>
                        
                        <h2 class="text-2xl font-bold text-gray-800 mt-4">
                            Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                        </h2>
                        <p class="text-gray-600">
                            <?= htmlspecialchars($doctor['specialty'] ?? 'General Practitioner') ?>
                        </p>
                        
                        <div class="mt-4 flex justify-center space-x-2">
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                ID: DOC-<?= str_pad($doctor['id'], 4, '0', STR_PAD_LEFT) ?>
                            </span>
                            <span class="px-3 py-1 <?= $doctor['status'] == 'available' ? 'bg-green-100 text-green-800' : ($doctor['status'] == 'busy' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?> rounded-full text-xs font-medium">
                                <i class="fas fa-circle mr-1 text-xs"></i>
                                <?= ucfirst($doctor['status'] ?? 'available') ?>
                            </span>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Member since</span>
                                <span class="font-semibold text-gray-800">
                                    <?= date('F Y', strtotime($doctor['created_at'])) ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm mt-3">
                                <span class="text-gray-500">Department</span>
                                <span class="font-semibold text-gray-800">
                                    <?= htmlspecialchars($doctor['category_name'] ?? 'Not assigned') ?>
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm mt-3">
                                <span class="text-gray-500">Total Patients</span>
                                <span class="font-semibold text-blue-600">
                                    <?= $stats['total_patients'] ?? 0 ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Status Toggle (View Mode) -->
                        <div class="mt-4 view-mode">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">Current Status</span>
                                    <span class="px-3 py-1 <?= $doctor['status'] == 'available' ? 'bg-green-100 text-green-800' : ($doctor['status'] == 'busy' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?> rounded-full text-xs font-medium">
                                        <?= ucfirst($doctor['status'] ?? 'available') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Toggle (Edit Mode) -->
                        <div class="edit-mode hidden mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Availability Status
                            </label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <?php foreach ($status_options as $option): ?>
                                    <option value="<?= $option ?>" <?= ($doctor['status'] ?? 'available') == $option ? 'selected' : '' ?>>
                                        <?= ucfirst($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Profile Details -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Professional Information Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-briefcase text-blue-600 mr-2"></i>
                            Professional Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Full Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="fullname" value="<?= htmlspecialchars($doctor['fullname']) ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Email (Read Only) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address
                                </label>
                                <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                    <?= htmlspecialchars($doctor['email']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle"></i> Email cannot be changed
                                </p>
                            </div>
                            
                            <!-- Phone Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Number
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['phone'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>" 
                                           placeholder="(555) 123-4567"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Specialty -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Specialty
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['specialty'] ?? 'General') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="specialty" value="<?= htmlspecialchars($doctor['specialty'] ?? '') ?>" 
                                           placeholder="e.g., Cardiologist"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Category/Department -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Department / Category
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['category_name'] ?? 'Not assigned') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Department</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= ($doctor['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['category_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personal Information Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Date of Birth -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= $doctor['date_of_birth'] ? date('F d, Y', strtotime($doctor['date_of_birth'])) : 'Not provided' ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="date" name="date_of_birth" value="<?= $doctor['date_of_birth'] ?? '' ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Blood Group -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Blood Group
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['blood_group'] ?? 'Not specified') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="blood_group" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Blood Group</option>
                                        <?php foreach ($blood_groups as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($doctor['blood_group'] ?? '') == $bg ? 'selected' : '' ?>>
                                                <?= $bg ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                            Clinic Address
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Address Line -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Street Address
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['address'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="address" value="<?= htmlspecialchars($doctor['address'] ?? '') ?>" 
                                           placeholder="Street address"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- City -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    City
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['city'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="city" value="<?= htmlspecialchars($doctor['city'] ?? '') ?>" 
                                           placeholder="City"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- State -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    State
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['state'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="state" value="<?= htmlspecialchars($doctor['state'] ?? '') ?>" 
                                           placeholder="State"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- ZIP Code -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    ZIP Code
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($doctor['zip_code'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="zip_code" value="<?= htmlspecialchars($doctor['zip_code'] ?? '') ?>" 
                                           placeholder="ZIP code"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Card -->
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-bar text-blue-600 mr-2"></i>
                            Practice Statistics
                        </h3>
                        
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div class="bg-white rounded-lg p-4 text-center">
                                <p class="text-2xl font-bold text-blue-600"><?= $stats['total_appointments'] ?? 0 ?></p>
                                <p class="text-xs text-gray-500">Total Appointments</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 text-center">
                                <p class="text-2xl font-bold text-green-600"><?= $stats['total_patients'] ?? 0 ?></p>
                                <p class="text-xs text-gray-500">Patients</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 text-center">
                                <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?? 0 ?></p>
                                <p class="text-xs text-gray-500">Pending</p>
                            </div>
                            <div class="bg-white rounded-lg p-4 text-center">
                                <p class="text-2xl font-bold text-purple-600"><?= $available_slots ?></p>
                                <p class="text-xs text-gray-500">Available Slots</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edit Mode Buttons -->
                    <div id="editButtons" class="hidden flex justify-end space-x-3">
                        <button type="button" onclick="cancelEdit()" 
                                class="px-6 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" name="update_profile" 
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium rounded-lg transition-all transform hover:scale-105 shadow-md">
                            <i class="fas fa-save mr-2"></i>
                            Save Changes
                        </button>
                    </div>
                    
                </div>
            </div>
        </form>
        
        <!-- Recent Activity & Quick Actions -->
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Recent Appointments -->
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-clock text-blue-600 mr-2"></i>
                    Recent Appointments
                </h3>
                
                <?php
                // Fetch recent appointments
                $stmt = $pdo->prepare("
                    SELECT 
                        a.id,
                        a.status,
                        ds.slot_time,
                        u.fullname as patient_name,
                        u.id as patient_id
                    FROM appointments a
                    JOIN doctor_slots ds ON a.slot_id = ds.id
                    JOIN users u ON a.patient_id = u.id
                    WHERE a.doctor_id = ?
                    ORDER BY ds.slot_time DESC
                    LIMIT 5
                ");
                $stmt->execute([$doctor_id]);
                $recent_appointments = $stmt->fetchAll();
                ?>
                
                <?php if (count($recent_appointments) > 0): ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_appointments as $apt): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-calendar-check text-blue-600 text-sm"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">
                                            <?= htmlspecialchars($apt['patient_name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('M d, Y - h:i A', strtotime($apt['slot_time'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?= $apt['status'] == 'approved' ? 'bg-green-100 text-green-800' : ($apt['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                    <?= ucfirst($apt['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No recent appointments</p>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                    Quick Actions
                </h3>
                
                <div class="space-y-3">
                    <a href="manage_slots.php" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors group">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-blue-700">Add Availability Slots</span>
                        <i class="fas fa-arrow-right ml-auto text-blue-500 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                    
                    <a href="pending_appointments.php" class="flex items-center p-3 bg-yellow-50 hover:bg-yellow-100 rounded-xl transition-colors group">
                        <div class="w-10 h-10 bg-yellow-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-yellow-700">Review Pending Appointments</span>
                        <?php if ($pending_count > 0): ?>
                            <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                                <?= $pending_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="messages.php" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-comments"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-green-700">Check Patient Messages</span>
                        <?php if ($unread_messages > 0): ?>
                            <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                                <?= $unread_messages ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="change_password.php" class="flex items-center p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-colors group">
                        <div class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-key"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-gray-700">Change Password</span>
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript for Edit Mode -->
<script>
    let editMode = false;
    
    function toggleEditMode() {
        editMode = !editMode;
        const viewElements = document.querySelectorAll('.view-mode');
        const editElements = document.querySelectorAll('.edit-mode');
        const editButtons = document.getElementById('editButtons');
        const editButtonText = document.getElementById('editButtonText');
        
        if (editMode) {
            // Switch to edit mode
            viewElements.forEach(el => el.classList.add('hidden'));
            editElements.forEach(el => el.classList.remove('hidden'));
            editButtons.classList.remove('hidden');
            editButtonText.textContent = 'Cancel Editing';
            editButtonText.parentElement.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            editButtonText.parentElement.classList.add('bg-gray-600', 'hover:bg-gray-700');
        } else {
            // Switch to view mode
            viewElements.forEach(el => el.classList.remove('hidden'));
            editElements.forEach(el => el.classList.add('hidden'));
            editButtons.classList.add('hidden');
            editButtonText.textContent = 'Edit Profile';
            editButtonText.parentElement.classList.add('bg-blue-600', 'hover:bg-blue-700');
            editButtonText.parentElement.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        }
    }
    
    function cancelEdit() {
        editMode = true; // This will toggle to false
        toggleEditMode();
        // Reload the page to reset form values
        location.reload();
    }
    
    // Auto-hide success/error messages after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.bg-green-50, .bg-red-50');
        alerts.forEach(alert => {
            if (alert.classList.contains('border-l-4')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }
        });
    }, 5000);
</script>

</body>
</html>