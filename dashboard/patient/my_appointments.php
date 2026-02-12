<?php
session_start();
require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

// Get patient details for sidebar
$stmt = $pdo->prepare("SELECT fullname, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$fullname = $user ? $user['fullname'] : 'Patient';
$email = $user ? $user['email'] : '';
$phone = $user ? ($user['phone'] ?? 'Not provided') : 'Not provided';
$member_since = $user ? date('F Y', strtotime($user['created_at'])) : date('F Y');

// Get initials for avatar
$name_parts = explode(' ', $fullname);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($fullname, 0, 2));
}

// Handle appointment cancellation
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $appointment_id = (int) $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get slot_id before updating status
        $stmt = $pdo->prepare("SELECT slot_id FROM appointments WHERE id = ? AND patient_id = ?");
        $stmt->execute([$appointment_id, $_SESSION['user_id']]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            // Update appointment status to cancelled
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$appointment_id]);
            
            // Make slot available again
            $stmt = $pdo->prepare("UPDATE doctor_slots SET is_booked = 0 WHERE id = ?");
            $stmt->execute([$appointment['slot_id']]);
            
            $pdo->commit();
            $success = "Appointment cancelled successfully.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error cancelling appointment.";
    }
    
    header("Location: my_appointments.php?success=" . urlencode($success ?? '') . "&error=" . urlencode($error ?? ''));
    exit;
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'upcoming';

// Fetch appointments based on filter with rating status
if ($filter === 'upcoming') {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            a.created_at as booked_at,
            ds.slot_time,
            d.id as doctor_id,
            d.fullname as doctor_name,
            d.specialty,
            d.phone as doctor_phone,
            d.email as doctor_email,
            dc.category_name,
            dc.id as category_id,
            (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as is_rated
        FROM appointments a
        JOIN doctor_slots ds ON a.slot_id = ds.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN doctor_categories dc ON d.category_id = dc.id
        WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status != 'cancelled'
        ORDER BY ds.slot_time ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
} elseif ($filter === 'past') {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            a.created_at as booked_at,
            ds.slot_time,
            d.id as doctor_id,
            d.fullname as doctor_name,
            d.specialty,
            d.phone as doctor_phone,
            d.email as doctor_email,
            dc.category_name,
            dc.id as category_id,
            (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as is_rated,
            (SELECT rating FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as given_rating
        FROM appointments a
        JOIN doctor_slots ds ON a.slot_id = ds.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN doctor_categories dc ON d.category_id = dc.id
        WHERE a.patient_id = ? AND (ds.slot_time < NOW() OR a.status = 'cancelled')
        ORDER BY ds.slot_time DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
} else {
    // All appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.status,
            a.created_at as booked_at,
            ds.slot_time,
            d.id as doctor_id,
            d.fullname as doctor_name,
            d.specialty,
            d.phone as doctor_phone,
            d.email as doctor_email,
            dc.category_name,
            dc.id as category_id,
            (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as is_rated,
            (SELECT rating FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as given_rating
        FROM appointments a
        JOIN doctor_slots ds ON a.slot_id = ds.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN doctor_categories dc ON d.category_id = dc.id
        WHERE a.patient_id = ?
        ORDER BY ds.slot_time DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
}

$appointments = $stmt->fetchAll();

// Get counts for statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN ds.slot_time >= NOW() AND a.status != 'cancelled' THEN 1 END) as upcoming,
        COUNT(CASE WHEN ds.slot_time < NOW() AND a.status != 'cancelled' THEN 1 END) as completed,
        COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$upcoming_count = $stats['upcoming'] ?? 0;
$completed_count = $stats['completed'] ?? 0;
$cancelled_count = $stats['cancelled'] ?? 0;
$total_count = $upcoming_count + $completed_count + $cancelled_count;

// Get unrated completed appointments count for notifications
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time < NOW() AND a.status = 'approved'
    AND NOT EXISTS (
        SELECT 1 FROM doctor_ratings dr 
        WHERE dr.appointment_id = a.id AND dr.patient_id = a.patient_id
    )
");
$stmt->execute([$_SESSION['user_id']]);
$unrated_count = $stmt->fetchColumn();

// Get success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$rating_success = $_GET['rated'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Appointments | MediTrack</title>
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
        .appointment-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .rating-stars i {
            color: #ffc107;
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
                    <h2 class="text-xl font-bold truncate"><?= htmlspecialchars($fullname) ?></h2>
                    <p class="text-sm text-blue-200 flex items-center mt-1">
                        <i class="fas fa-envelope mr-2 text-xs"></i>
                        <?= htmlspecialchars($email) ?>
                    </p>
                    <p class="text-xs text-blue-300 mt-2 flex items-center">
                        <i class="fas fa-phone mr-2"></i>
                        <?= htmlspecialchars($phone) ?>
                    </p>
                    <p class="text-xs text-blue-300 mt-1 flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Member since <?= $member_since ?>
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-3 gap-2 bg-blue-700/30 rounded-xl p-3">
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $upcoming_count ?></p>
                    <p class="text-xs text-blue-200">Upcoming</p>
                </div>
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $completed_count ?></p>
                    <p class="text-xs text-blue-200">Completed</p>
                </div>
                <div class="text-center">
                    <p class="text-xl font-bold text-white"><?= $cancelled_count ?></p>
                    <p class="text-xs text-blue-200">Cancelled</p>
                </div>
            </div>
            
            <!-- Rating Reminder -->
            <?php if ($unrated_count > 0): ?>
                <div class="mt-4 bg-yellow-500/20 rounded-xl p-3 border border-yellow-400/30">
                    <div class="flex items-center">
                        <i class="fas fa-star text-yellow-300 mr-2"></i>
                        <p class="text-xs text-yellow-100">
                            You have <span class="font-bold"><?= $unrated_count ?></span> unrated <?= $unrated_count == 1 ? 'appointment' : 'appointments' ?>
                        </p>
                    </div>
                    <a href="#unrated" class="text-xs text-yellow-200 hover:text-yellow-100 underline mt-1 inline-block">
                        Rate now →
                    </a>
                </div>
            <?php endif; ?>
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
            <a href="my_appointments.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
            <a href="profile.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
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
                        <span class="text-gray-700 font-medium">My Appointments</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                My Appointments
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Manage and track all your appointments
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0">
                    <a href="categories.php" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Book New Appointment
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between animate-pulse">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['rated']) && $_GET['rated'] == 'success'): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-star text-yellow-400 text-xl mr-3"></i>
                    <span>Thank you! Your rating has been submitted successfully.</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'already_rated'): ?>
            <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                    <span>You have already rated this appointment.</span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-blue-700 hover:text-blue-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Appointments</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $total_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Upcoming</p>
                        <p class="text-2xl font-bold text-green-600"><?= $upcoming_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Completed</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $completed_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-purple-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Need Rating</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $unrated_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-star text-yellow-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="border-b border-gray-200">
                <div class="flex flex-wrap px-4">
                    <a href="?filter=upcoming" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'upcoming' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-clock mr-2"></i>
                        Upcoming (<?= $upcoming_count ?>)
                    </a>
                    <a href="?filter=past" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'past' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-history mr-2"></i>
                        Past & Cancelled
                    </a>
                    <a href="?filter=all" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'all' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-list mr-2"></i>
                        All Appointments
                    </a>
                </div>
            </div>
        </div>

        <!-- Unrated Appointments Banner -->
        <?php if ($filter === 'past' && $unrated_count > 0): ?>
            <div id="unrated" class="mb-6 bg-gradient-to-r from-yellow-400 to-yellow-500 rounded-xl p-4 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-star text-2xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="font-semibold">You have <?= $unrated_count ?> unrated <?= $unrated_count == 1 ? 'appointment' : 'appointments' ?></h3>
                            <p class="text-sm text-yellow-100">Share your feedback to help other patients</p>
                        </div>
                    </div>
                    <a href="#appointments" class="px-4 py-2 bg-white text-yellow-600 rounded-lg text-sm font-semibold hover:bg-yellow-50 transition-colors">
                        Rate Now
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Appointments List -->
        <div id="appointments">
            <?php if (count($appointments) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($appointments as $apt): 
                        $is_upcoming = strtotime($apt['slot_time']) > time() && $apt['status'] !== 'cancelled';
                        $is_past = strtotime($apt['slot_time']) < time() && $apt['status'] !== 'cancelled';
                        $is_cancelled = $apt['status'] === 'cancelled';
                        $is_pending = $apt['status'] === 'pending';
                        $is_approved = $apt['status'] === 'approved';
                        $is_rated = $apt['is_rated'] ?? 0;
                        $given_rating = $apt['given_rating'] ?? 0;
                        
                        // Status color mapping
                        $status_colors = [
                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            'approved' => 'bg-green-100 text-green-800 border-green-200',
                            'cancelled' => 'bg-red-100 text-red-800 border-red-200',
                            'completed' => 'bg-blue-100 text-blue-800 border-blue-200'
                        ];
                        $status_color = $status_colors[$apt['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                    ?>
                        <div class="appointment-card bg-white rounded-xl shadow-md hover:shadow-lg transition-all overflow-hidden border border-gray-100">
                            <div class="p-6">
                                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                                    <!-- Left: Date & Time -->
                                    <div class="flex items-start mb-4 lg:mb-0">
                                        <div class="text-center min-w-[80px] <?= $is_cancelled ? 'opacity-50' : '' ?>">
                                            <div class="text-3xl font-bold text-gray-800">
                                                <?= date('d', strtotime($apt['slot_time'])) ?>
                                            </div>
                                            <div class="text-sm font-semibold text-gray-600">
                                                <?= date('M', strtotime($apt['slot_time'])) ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?= date('Y', strtotime($apt['slot_time'])) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-clock <?= $is_cancelled ? 'text-gray-400' : 'text-blue-500' ?> mr-2"></i>
                                                <span class="text-lg font-semibold text-gray-800">
                                                    <?= date('h:i A', strtotime($apt['slot_time'])) ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <i class="fas fa-tag text-gray-400 mr-2 text-sm"></i>
                                                <span class="text-sm text-gray-600">
                                                    <?= htmlspecialchars($apt['category_name']) ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <i class="fas fa-calendar-check text-gray-400 mr-2 text-sm"></i>
                                                <span class="text-xs text-gray-500">
                                                    Booked on <?= date('M d, Y', strtotime($apt['booked_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Middle: Doctor Info -->
                                    <div class="flex-1 lg:ml-8 mb-4 lg:mb-0">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 <?= $is_cancelled ? 'bg-gradient-to-br from-gray-500 to-gray-600' : 'bg-gradient-to-br from-blue-500 to-blue-600' ?> rounded-full flex items-center justify-center text-white font-bold text-lg">
                                                <?= strtoupper(substr($apt['doctor_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-lg font-bold text-gray-800">
                                                    Dr. <?= htmlspecialchars($apt['doctor_name']) ?>
                                                </h3>
                                                <p class="text-sm text-gray-600">
                                                    <?= htmlspecialchars($apt['specialty']) ?>
                                                </p>
                                                <?php if ($is_upcoming): ?>
                                                    <a href="mailto:<?= $apt['doctor_email'] ?>" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
                                                        <i class="fas fa-envelope mr-1"></i>
                                                        Contact Doctor
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right: Status & Actions -->
                                    <div class="flex flex-col items-start lg:items-end">
                                        <span class="status-badge px-4 py-2 rounded-full text-sm font-semibold <?= $status_color ?> border">
                                            <?php 
                                                if ($is_cancelled) echo '❌ Cancelled';
                                                else if ($is_pending) echo '⏳ Pending Confirmation';
                                                else if ($is_approved && $is_upcoming) echo '✅ Confirmed';
                                                else if ($is_approved && $is_past) echo '✓ Completed';
                                                else echo ucfirst($apt['status']);
                                            ?>
                                        </span>
                                        
                                        <!-- Rating Stars (if rated) -->
                                        <?php if ($is_rated && $given_rating > 0): ?>
                                            <div class="flex items-center mt-2">
                                                <div class="flex items-center rating-stars mr-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $given_rating ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="text-xs text-gray-500">You rated</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-4 flex space-x-2">
                                            <?php if ($is_upcoming && !$is_cancelled): ?>
                                                <a href="reschedule_appointment.php?id=<?= $apt['id'] ?>" 
                                                   class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition-colors">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Reschedule
                                                </a>
                                                <a href="?cancel=1&id=<?= $apt['id'] ?>" 
                                                   onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                                   class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 rounded-lg text-sm font-medium transition-colors">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Cancel
                                                </a>
                                            <?php elseif ($is_past && !$is_cancelled): ?>
                                                <a href="book_appointment.php?doctor_id=<?= $apt['doctor_id'] ?>" 
                                                   class="px-4 py-2 bg-green-50 hover:bg-green-100 text-green-700 rounded-lg text-sm font-medium transition-colors">
                                                    <i class="fas fa-redo-alt mr-1"></i>
                                                    Book Again
                                                </a>
                                                
                                                <?php if (!$is_rated): ?>
                                                    <a href="rate_doctor.php?doctor_id=<?= $apt['doctor_id'] ?>&appointment_id=<?= $apt['id'] ?>" 
                                                       class="px-4 py-2 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 rounded-lg text-sm font-medium transition-colors animate-pulse">
                                                        <i class="fas fa-star mr-1"></i>
                                                        Rate This Visit
                                                    </a>
                                                <?php else: ?>
                                                    <span class="px-4 py-2 bg-gray-100 text-gray-500 rounded-lg text-sm font-medium">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Rated
                                                    </span>
                                                <?php endif; ?>
                                                
                                            <?php elseif ($is_cancelled): ?>
                                                <a href="book_appointment.php?doctor_id=<?= $apt['doctor_id'] ?>" 
                                                   class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition-colors">
                                                    <i class="fas fa-calendar-plus mr-1"></i>
                                                    Book Again
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="appointment_details.php?id=<?= $apt['id'] ?>" 
                                               class="px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                                                <i class="fas fa-eye mr-1"></i>
                                                Details
                                            </a>
                                        </div>
                                        
                                        <?php if ($is_pending): ?>
                                            <p class="text-xs text-yellow-600 mt-2 flex items-center">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Awaiting doctor confirmation
                                            </p>
                                        <?php elseif ($is_approved && $is_upcoming): ?>
                                            <p class="text-xs text-green-600 mt-2 flex items-center">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Appointment confirmed
                                            </p>
                                        <?php elseif ($is_approved && $is_past && !$is_rated): ?>
                                            <p class="text-xs text-yellow-600 mt-2 flex items-center">
                                                <i class="fas fa-star mr-1"></i>
                                                Please rate your experience
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Appointment ID & Quick Info -->
                            <div class="bg-gray-50 px-6 py-3 border-t border-gray-100">
                                <div class="flex flex-wrap items-center justify-between text-xs text-gray-600">
                                    <div class="flex items-center">
                                        <i class="fas fa-hashtag mr-1"></i>
                                        Appointment ID: APT-<?= str_pad($apt['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <?php if ($is_upcoming && !$is_cancelled): ?>
                                        <div class="flex items-center text-green-600">
                                            <i class="fas fa-bell mr-1"></i>
                                            Reminder will be sent 24h before
                                        </div>
                                    <?php elseif ($is_past && !$is_cancelled && !$is_rated): ?>
                                        <div class="flex items-center text-yellow-600">
                                            <i class="fas fa-star mr-1"></i>
                                            Your feedback helps others
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-calendar-times text-gray-400 text-4xl"></i>
                    </div>
                    
                    <?php if ($filter === 'upcoming'): ?>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">No Upcoming Appointments</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You don't have any upcoming appointments. Book your first appointment with our specialist doctors.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="categories.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md">
                                <i class="fas fa-calendar-plus mr-2"></i>
                                Book Appointment
                            </a>
                            <a href="?filter=past" class="inline-flex items-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                                <i class="fas fa-history mr-2"></i>
                                View Past Appointments
                            </a>
                        </div>
                    <?php elseif ($filter === 'past'): ?>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">No Past Appointments</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You haven't had any appointments yet. Book your first appointment to get started.
                        </p>
                        <a href="categories.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Book Appointment
                        </a>
                    <?php else: ?>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">No Appointments Found</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You haven't booked any appointments yet. Start your healthcare journey with MediTrack.
                        </p>
                        <a href="categories.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Book Your First Appointment
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Help Section -->
        <div class="mt-8 bg-blue-50 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row items-center justify-between">
                <div class="flex items-center mb-4 sm:mb-0">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-question-circle text-white text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold text-gray-800">Need help with your appointment?</h4>
                        <p class="text-sm text-gray-600">Contact our support team for assistance</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a href="tel:+1234567890" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg shadow-sm transition-colors border border-gray-200">
                        <i class="fas fa-phone-alt mr-2 text-blue-600"></i>
                        Call Support
                    </a>
                    <a href="mailto:support@meditrack.com" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg shadow-sm transition-colors border border-gray-200">
                        <i class="fas fa-envelope mr-2 text-blue-600"></i>
                        Email Us
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript -->
<script>
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
    
    // Smooth scroll to appointments section
    document.querySelectorAll('a[href="#appointments"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#appointments').scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script>

</body>
</html>