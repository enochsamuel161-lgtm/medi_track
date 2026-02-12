<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// Fetch complete patient profile
$stmt = $pdo->prepare("
    SELECT 
        id, 
        fullname, 
        email, 
        phone, 
        created_at,
        date_of_birth,
        blood_group,
        allergies,
        emergency_contact,
        emergency_name,
        address,
        city,
        state,
        zip_code
    FROM users 
    WHERE id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: ../../public/logout.php");
    exit;
}

// Get statistics for profile
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN ds.slot_time >= NOW() AND a.status != 'cancelled' THEN 1 END) as upcoming,
        COUNT(CASE WHEN ds.slot_time < NOW() AND a.status != 'cancelled' THEN 1 END) as completed
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ?
");
$stmt->execute([$patient_id]);
$stats = $stmt->fetch();

// Get upcoming appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status IN ('pending', 'approved')
");
$stmt->execute([$patient_id]);
$upcoming_count = $stmt->fetchColumn();

// Get initials for avatar
$name_parts = explode(' ', $patient['fullname']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($patient['fullname'], 0, 2));
}

// Handle profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'] ?? '';
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $blood_group = $_POST['blood_group'] ?? null;
    $allergies = $_POST['allergies'] ?? '';
    $emergency_name = $_POST['emergency_name'] ?? '';
    $emergency_contact = $_POST['emergency_contact'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    
    try {
        // FIXED: Count the placeholders - there are 11 fields + 1 WHERE clause = 12 placeholders
        $stmt = $pdo->prepare("
            UPDATE users SET
                fullname = ?,
                phone = ?,
                date_of_birth = ?,
                blood_group = ?,
                allergies = ?,
                emergency_name = ?,
                emergency_contact = ?,
                address = ?,
                city = ?,
                state = ?,
                zip_code = ?
            WHERE id = ?
        ");
        
        // FIXED: Execute with exactly 12 parameters (11 SET + 1 WHERE)
        $stmt->execute([
            $fullname,           // 1. fullname
            $phone,             // 2. phone
            $date_of_birth,     // 3. date_of_birth
            $blood_group,       // 4. blood_group
            $allergies,         // 5. allergies
            $emergency_name,    // 6. emergency_name
            $emergency_contact, // 7. emergency_contact
            $address,           // 8. address
            $city,              // 9. city
            $state,             // 10. state
            $zip_code,          // 11. zip_code
            $patient_id         // 12. id (WHERE clause)
        ]);
        
        // Update session name
        $_SESSION['fullname'] = $fullname;
        
        $success_message = "Profile updated successfully!";
        
        // Refresh patient data
        $stmt = $pdo->prepare("
            SELECT 
                id, fullname, email, phone, created_at,
                date_of_birth, blood_group, allergies,
                emergency_contact, emergency_name, address,
                city, state, zip_code
            FROM users WHERE id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Blood groups array
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Profile | MediTrack</title>
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
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same enhanced style as patient dashboard -->
    <aside class="lg:w-80 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
        <!-- Profile Section -->
        <div class="p-6">
            <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-lg">
                    <span class="text-3xl font-bold text-blue-800"><?= htmlspecialchars($initials) ?></span>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold truncate"><?= htmlspecialchars($patient['fullname']) ?></h2>
                    <p class="text-sm text-blue-200 flex items-center mt-1">
                        <i class="fas fa-envelope mr-2 text-xs"></i>
                        <?= htmlspecialchars($patient['email']) ?>
                    </p>
                    <p class="text-xs text-blue-300 mt-2 flex items-center">
                        <i class="fas fa-phone mr-2"></i>
                        <?= htmlspecialchars($patient['phone'] ?? 'Not provided') ?>
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-3 gap-2 bg-blue-700/30 rounded-xl p-3">
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $stats['total_appointments'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Total</p>
                </div>
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $stats['upcoming'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Upcoming</p>
                </div>
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $stats['completed'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Completed</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="categories.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-plus w-5 h-5 mr-3"></i>
                <span>Book Appointment</span>
            </a>
            <a href="my_appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>My Appointments</span>
                <?php if ($upcoming_count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $upcoming_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="doctors.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-user-md w-5 h-5 mr-3"></i>
                <span>Find Doctors</span>
            </a>
            <a href="medical_records.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-notes-medical w-5 h-5 mr-3"></i>
                <span>Medical Records</span>
            </a>
            <a href="messages.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-comments w-5 h-5 mr-3"></i>
                <span>Messages</span>
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
                            <i class="fas fa-user-circle text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                My Profile
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Manage your personal information and health details
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0">
                    <button onclick="toggleEditMode()" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-edit mr-2"></i>
                        <span id="editButtonText">Edit Profile</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between">
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
                            <?= htmlspecialchars($patient['fullname']) ?>
                        </h2>
                        <p class="text-gray-600">Patient</p>
                        
                        <div class="mt-4 flex justify-center space-x-2">
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                ID: PAT-<?= str_pad($patient['id'], 6, '0', STR_PAD_LEFT) ?>
                            </span>
                        </div>
                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500">Member since</span>
                                <span class="font-semibold text-gray-800">
                                    <?= date('F Y', strtotime($patient['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Profile Details -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Personal Information Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Full Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Full Name
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['fullname']) ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="fullname" value="<?= htmlspecialchars($patient['fullname']) ?>" 
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
                                    <?= htmlspecialchars($patient['email']) ?>
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
                                        <?= htmlspecialchars($patient['phone'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>" 
                                           placeholder="(555) 123-4567"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Date of Birth -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= $patient['date_of_birth'] ? date('F d, Y', strtotime($patient['date_of_birth'])) : 'Not provided' ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="date" name="date_of_birth" value="<?= $patient['date_of_birth'] ?? '' ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Information Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-heartbeat text-red-500 mr-2"></i>
                            Medical Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Blood Group -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Blood Group
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['blood_group'] ?? 'Not specified') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="blood_group" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Blood Group</option>
                                        <?php foreach ($blood_groups as $bg): ?>
                                            <option value="<?= $bg ?>" <?= ($patient['blood_group'] ?? '') == $bg ? 'selected' : '' ?>>
                                                <?= $bg ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Allergies -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Allergies
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['allergies'] ?? 'None reported') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="allergies" value="<?= htmlspecialchars($patient['allergies'] ?? '') ?>" 
                                           placeholder="e.g., Penicillin, Peanuts"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Emergency Contact Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-ambulance text-orange-500 mr-2"></i>
                            Emergency Contact
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Contact Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Contact Name
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['emergency_name'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="emergency_name" value="<?= htmlspecialchars($patient['emergency_name'] ?? '') ?>" 
                                           placeholder="Full name"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Contact Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Contact Phone
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['emergency_contact'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="tel" name="emergency_contact" value="<?= htmlspecialchars($patient['emergency_contact'] ?? '') ?>" 
                                           placeholder="(555) 123-4567"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Card -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt text-green-500 mr-2"></i>
                            Address
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Address Line -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Street Address
                                </label>
                                <div class="view-mode">
                                    <p class="text-gray-800 py-2 px-3 bg-gray-50 rounded-lg">
                                        <?= htmlspecialchars($patient['address'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="address" value="<?= htmlspecialchars($patient['address'] ?? '') ?>" 
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
                                        <?= htmlspecialchars($patient['city'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="city" value="<?= htmlspecialchars($patient['city'] ?? '') ?>" 
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
                                        <?= htmlspecialchars($patient['state'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="state" value="<?= htmlspecialchars($patient['state'] ?? '') ?>" 
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
                                        <?= htmlspecialchars($patient['zip_code'] ?? 'Not provided') ?>
                                    </p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="zip_code" value="<?= htmlspecialchars($patient['zip_code'] ?? '') ?>" 
                                           placeholder="ZIP code"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
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
        
        <!-- Recent Activity -->
        <div class="mt-8 bg-white rounded-2xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-history text-blue-600 mr-2"></i>
                Recent Activity
            </h3>
            
            <?php
            // Fetch recent appointments
            $stmt = $pdo->prepare("
                SELECT 
                    ds.slot_time,
                    d.fullname as doctor_name,
                    d.specialty,
                    a.status
                FROM appointments a
                JOIN doctor_slots ds ON a.slot_id = ds.id
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.patient_id = ?
                ORDER BY ds.slot_time DESC
                LIMIT 5
            ");
            $stmt->execute([$patient_id]);
            $recent_activities = $stmt->fetchAll();
            ?>
            
            <?php if (count($recent_activities) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-check text-blue-600 text-sm"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-800">
                                        Appointment with Dr. <?= htmlspecialchars($activity['doctor_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?= date('M d, Y - h:i A', strtotime($activity['slot_time'])) ?>
                                    </p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?= $activity['status'] == 'approved' ? 'bg-green-100 text-green-800' : ($activity['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                <?= ucfirst($activity['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No recent activity</p>
            <?php endif; ?>
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