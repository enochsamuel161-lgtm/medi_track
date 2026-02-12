<?php
session_start();
require "../../config/database.php";

// Only allow logged-in doctors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../../public/login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];
$patient_id = $_GET['id'] ?? 0;

if (!$patient_id) {
    header("Location: patients.php");
    exit;
}

// Ensure doctor fullname is set
if (!isset($_SESSION['fullname']) || empty($_SESSION['fullname'])) {
    $stmt = $pdo->prepare("SELECT fullname, specialty, email, phone FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    $_SESSION['fullname'] = $doctor ? $doctor['fullname'] : "Doctor";
    $_SESSION['specialty'] = $doctor ? $doctor['specialty'] : "General";
}

// Fetch patient details
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.phone,
        u.date_of_birth,
        u.blood_group,
        u.allergies,
        u.address,
        u.city,
        u.state,
        u.zip_code,
        u.created_at,
        (SELECT COUNT(*) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ? AND ds.slot_time >= NOW() 
         AND a.status != 'cancelled') as upcoming_appointments,
        (SELECT COUNT(*) FROM messages m 
         WHERE m.patient_id = u.id AND m.doctor_id = ? AND m.status = 'sent') as unread_messages,
        (SELECT AVG(rating) FROM doctor_ratings dr 
         WHERE dr.patient_id = u.id AND dr.doctor_id = ?) as patient_rating
    FROM users u
    WHERE u.id = ? AND u.role = 'patient'
");
$stmt->execute([$doctor_id, $doctor_id, $doctor_id, $doctor_id, $patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header("Location: patients.php");
    exit;
}

// Fetch appointment history with this doctor
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.status,
        a.notes as doctor_notes,
        a.created_at as booked_at,
        ds.slot_time as appointment_date,
        ds.notes as slot_notes,
        (SELECT rating FROM doctor_ratings dr WHERE dr.appointment_id = a.id) as rating,
        (SELECT review FROM doctor_ratings dr WHERE dr.appointment_id = a.id) as review
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND a.doctor_id = ?
    ORDER BY ds.slot_time DESC
");
$stmt->execute([$patient_id, $doctor_id]);
$appointments = $stmt->fetchAll();

// Fetch message history with this patient
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        CASE 
            WHEN m.doctor_id = ? THEN 'You'
            ELSE u.fullname
        END as sender_name
    FROM messages m
    LEFT JOIN users u ON m.patient_id = u.id
    WHERE (m.patient_id = ? AND m.doctor_id = ?) 
       OR (m.patient_id = ? AND m.doctor_id = ?)
    ORDER BY m.created_at DESC
    LIMIT 10
");
$stmt->execute([$doctor_id, $patient_id, $doctor_id, $doctor_id, $patient_id]);
$recent_messages = $stmt->fetchAll();

// Get patient vitals (if you have a vitals table)
$vitals = [];
// $stmt = $pdo->prepare("SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
// $stmt->execute([$patient_id]);
// $vitals = $stmt->fetch();

// Calculate age
$age = $patient['date_of_birth'] ? (new DateTime())->diff(new DateTime($patient['date_of_birth']))->y : null;

// Get initials for avatar
$name_parts = explode(' ', $patient['fullname']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($patient['fullname'], 0, 2));
}

// Get doctor initials for sidebar
$doctor_name_parts = explode(' ', $_SESSION['fullname']);
$doctor_initials = '';
if (count($doctor_name_parts) >= 2) {
    $doctor_initials = strtoupper(substr($doctor_name_parts[0], 0, 1) . substr($doctor_name_parts[1], 0, 1));
} else {
    $doctor_initials = strtoupper(substr($_SESSION['fullname'], 0, 2));
}

// Get counts for sidebar badges
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ? AND ds.slot_time >= NOW() AND a.status = 'pending'
");
$stmt->execute([$doctor_id]);
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE doctor_id = ? AND status = 'sent'
");
$stmt->execute([$doctor_id]);
$unread_messages = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Patient Details | <?= htmlspecialchars($patient['fullname']) ?></title>
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
        .info-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .timeline-item {
            position: relative;
            padding-left: 28px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #3b82f6;
            border: 2px solid white;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 24px;
            bottom: -16px;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-item:last-child::after {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Doctor sidebar -->
    <aside class="lg:w-80 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
        <!-- Doctor Profile Section -->
        <div class="p-6">
            <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-lg">
                    <span class="text-3xl font-bold text-blue-800"><?= htmlspecialchars($doctor_initials) ?></span>
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold truncate">Dr. <?= htmlspecialchars($_SESSION['fullname']) ?></h2>
                    <p class="text-sm text-blue-200 flex items-center mt-1">
                        <i class="fas fa-stethoscope mr-2 text-xs"></i>
                        <?= htmlspecialchars($_SESSION['specialty'] ?? 'General') ?>
                    </p>
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
            <a href="messages.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-comments w-5 h-5 mr-3"></i>
                <span>Messages</span>
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
                        <a href="patients.php" class="hover:text-blue-600">My Patients</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Patient Details</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-user-circle text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Patient Profile
                            </h1>
                            <p class="text-gray-600 mt-1">
                                View detailed patient information and medical history
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <a href="messages.php?patient=<?= $patient['id'] ?>" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-comment mr-2"></i>
                        Send Message
                    </a>
                    <a href="appointments.php?patient=<?= $patient['id'] ?>" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-calendar-plus mr-2"></i>
                        New Appointment
                    </a>
                </div>
            </div>
        </div>

        <!-- Patient Profile Header Card -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 h-32"></div>
            <div class="px-6 pb-6 relative">
                <div class="flex flex-col sm:flex-row sm:items-end -mt-16">
                    <!-- Patient Avatar -->
                    <div class="flex-shrink-0">
                        <div class="w-28 h-28 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl border-4 border-white shadow-xl flex items-center justify-center text-white font-bold text-4xl">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                    </div>
                    
                    <!-- Patient Basic Info -->
                    <div class="flex-1 sm:ml-6 mt-4 sm:mt-0">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-3xl font-bold text-gray-800">
                                    <?= htmlspecialchars($patient['fullname']) ?>
                                </h2>
                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                        <i class="fas fa-id-card mr-1"></i>
                                        ID: PAT-<?= str_pad($patient['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                                        <i class="fas fa-calendar mr-1"></i>
                                        Patient since <?= date('M Y', strtotime($patient['created_at'])) ?>
                                    </span>
                                    <?php if ($patient['total_visits'] > 0): ?>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                                            <i class="fas fa-heartbeat mr-1"></i>
                                            <?= $patient['total_visits'] ?> visits
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="mt-4 sm:mt-0 flex items-center space-x-4">
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-gray-800"><?= $patient['upcoming_appointments'] ?? 0 ?></p>
                                    <p class="text-xs text-gray-500">Upcoming</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-gray-800"><?= $patient['unread_messages'] ?? 0 ?></p>
                                    <p class="text-xs text-gray-500">Unread</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold text-gray-800"><?= $patient['patient_rating'] ? number_format($patient['patient_rating'], 1) : '—' ?></p>
                                    <p class="text-xs text-gray-500">Rating</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column - Personal & Medical Info -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Personal Information Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user text-blue-600 mr-2"></i>
                        Personal Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-envelope text-blue-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Email Address</p>
                                <p class="text-sm font-medium text-gray-800 break-all">
                                    <?= htmlspecialchars($patient['email']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-phone-alt text-green-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Phone Number</p>
                                <p class="text-sm font-medium text-gray-800">
                                    <?= htmlspecialchars($patient['phone'] ?? 'Not provided') ?>
                                </p>
                                <?php if ($patient['phone']): ?>
                                    <a href="tel:<?= $patient['phone'] ?>" class="text-xs text-blue-600 hover:underline mt-1 inline-block">
                                        <i class="fas fa-phone mr-1"></i>Call
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-birthday-cake text-yellow-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Date of Birth</p>
                                <p class="text-sm font-medium text-gray-800">
                                    <?= $patient['date_of_birth'] ? date('F d, Y', strtotime($patient['date_of_birth'])) : 'Not provided' ?>
                                </p>
                                <?php if ($age): ?>
                                    <p class="text-xs text-gray-500 mt-1">Age: <?= $age ?> years</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($patient['address']): ?>
                        <div class="flex items-start">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-purple-600 text-sm"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-xs text-gray-500">Address</p>
                                <p class="text-sm font-medium text-gray-800">
                                    <?= htmlspecialchars($patient['address']) ?>
                                </p>
                                <?php if ($patient['city'] || $patient['state']): ?>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($patient['city'] ?? '') ?>, 
                                        <?= htmlspecialchars($patient['state'] ?? '') ?> 
                                        <?= htmlspecialchars($patient['zip_code'] ?? '') ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Medical Information Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-heartbeat text-red-500 mr-2"></i>
                        Medical Information
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="text-sm text-gray-600">Blood Group</span>
                            <span class="text-lg font-bold <?= $patient['blood_group'] ? 'text-red-600' : 'text-gray-400' ?>">
                                <?= htmlspecialchars($patient['blood_group'] ?? '—') ?>
                            </span>
                        </div>
                        
                        <div class="p-3 bg-gray-50 rounded-lg">
                            <p class="text-sm text-gray-600 mb-2">Allergies</p>
                            <?php if (!empty($patient['allergies'])): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $allergy_list = explode(',', $patient['allergies']);
                                    foreach ($allergy_list as $allergy): 
                                    ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                            <?= htmlspecialchars(trim($allergy)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 italic">No known allergies</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Emergency Contact (if available) -->
                        <?php if (!empty($patient['emergency_contact'])): ?>
                        <div class="p-3 bg-yellow-50 rounded-lg">
                            <p class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-ambulance text-yellow-600 mr-2"></i>
                                Emergency Contact
                            </p>
                            <p class="text-sm text-gray-800"><?= htmlspecialchars($patient['emergency_name'] ?? 'Not specified') ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($patient['emergency_contact']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Vitals (if available) -->
                    <?php if (!empty($vitals)): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Latest Vitals</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 p-2 rounded text-center">
                                <p class="text-xs text-gray-500">BP</p>
                                <p class="text-sm font-bold"><?= $vitals['bp_systolic'] ?? '—' ?>/<?= $vitals['bp_diastolic'] ?? '—' ?></p>
                            </div>
                            <div class="bg-gray-50 p-2 rounded text-center">
                                <p class="text-xs text-gray-500">HR</p>
                                <p class="text-sm font-bold"><?= $vitals['heart_rate'] ?? '—' ?> bpm</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                        Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <a href="messages.php?patient=<?= $patient['id'] ?>" 
                           class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors group">
                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-comment"></i>
                            </div>
                            <span class="ml-3 font-medium text-gray-700 group-hover:text-blue-700">Send Message</span>
                            <?php if ($patient['unread_messages'] > 0): ?>
                                <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                                    <?= $patient['unread_messages'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="appointments.php?patient=<?= $patient['id'] ?>" 
                           class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <span class="ml-3 font-medium text-gray-700 group-hover:text-green-700">Schedule Appointment</span>
                        </a>
                        
                        <a href="prescriptions.php?patient=<?= $patient['id'] ?>" 
                           class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors group">
                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-prescription"></i>
                            </div>
                            <span class="ml-3 font-medium text-gray-700 group-hover:text-purple-700">Write Prescription</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Medical History & Appointments -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Appointment History Card -->
                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-check text-blue-600"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-white ml-3">Appointment History</h3>
                        </div>
                        <span class="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
                            <?= count($appointments) ?> total
                        </span>
                    </div>
                    
                    <div class="p-6">
                        <?php if (count($appointments) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($appointments as $apt): ?>
                                    <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                                            <!-- Date & Time -->
                                            <div class="flex items-start mb-3 sm:mb-0">
                                                <div class="text-center min-w-[60px]">
                                                    <div class="text-xl font-bold text-gray-800">
                                                        <?= date('d', strtotime($apt['appointment_date'])) ?>
                                                    </div>
                                                    <div class="text-xs font-semibold text-gray-600">
                                                        <?= date('M', strtotime($apt['appointment_date'])) ?>
                                                    </div>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock text-blue-500 mr-1 text-xs"></i>
                                                        <span class="text-sm font-medium text-gray-700">
                                                            <?= date('h:i A', strtotime($apt['appointment_date'])) ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center mt-1">
                                                        <span class="px-2 py-0.5 text-xs rounded-full 
                                                            <?= $apt['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                               ($apt['status'] == 'approved' ? 'bg-blue-100 text-blue-800' : 
                                                               ($apt['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                               'bg-red-100 text-red-800')) ?>">
                                                            <?= ucfirst($apt['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Notes & Rating -->
                                            <div class="flex-1 sm:ml-6">
                                                <?php if (!empty($apt['doctor_notes'])): ?>
                                                    <div class="mb-2">
                                                        <p class="text-xs text-gray-500">Doctor's Notes</p>
                                                        <p class="text-sm text-gray-700"><?= htmlspecialchars($apt['doctor_notes']) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($apt['rating']): ?>
                                                    <div class="flex items-center">
                                                        <span class="text-xs text-gray-500 mr-2">Patient Rating:</span>
                                                        <div class="flex items-center">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star text-xs <?= $i <= $apt['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <?php if ($apt['review']): ?>
                                                            <span class="text-xs text-gray-500 ml-2 italic">"<?= htmlspecialchars(substr($apt['review'], 0, 50)) ?>..."</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-600">No appointment history with this patient</p>
                                <a href="appointments.php?patient=<?= $patient['id'] ?>" 
                                   class="inline-block mt-4 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    + Schedule first appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Messages Card -->
                <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex justify-between items-center">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                <i class="fas fa-comments text-green-600"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-white ml-3">Recent Messages</h3>
                        </div>
                        <a href="messages.php?patient=<?= $patient['id'] ?>" class="text-white hover:text-green-100 text-sm">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="p-6">
                        <?php if (count($recent_messages) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_messages as $msg): ?>
                                    <div class="flex items-start <?= $msg['doctor_id'] == $doctor_id ? 'justify-end' : '' ?>">
                                        <?php if ($msg['doctor_id'] != $doctor_id): ?>
                                            <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                                <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="<?= $msg['doctor_id'] == $doctor_id ? 'bg-blue-100' : 'bg-gray-100' ?> rounded-lg px-4 py-2 max-w-[70%] <?= $msg['doctor_id'] == $doctor_id ? 'mr-0 ml-auto' : 'ml-3' ?>">
                                            <p class="text-sm text-gray-800"><?= htmlspecialchars($msg['message']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= date('M d, h:i A', strtotime($msg['created_at'])) ?>
                                            </p>
                                        </div>
                                        
                                        <?php if ($msg['doctor_id'] == $doctor_id): ?>
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0 ml-3">
                                                <?= $doctor_initials ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="messages.php?patient=<?= $patient['id'] ?>" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <i class="fas fa-reply mr-1"></i>
                                    Reply to patient
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-comments text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-600">No messages yet with this patient</p>
                                <a href="messages.php?patient=<?= $patient['id'] ?>" 
                                   class="inline-block mt-4 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <i class="fas fa-comment mr-1"></i>
                                    Send first message
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Timeline Card (if you want to show a health timeline) -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-history text-blue-600 mr-2"></i>
                        Patient Timeline
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="timeline-item">
                            <p class="text-sm font-medium text-gray-800">Patient registered</p>
                            <p class="text-xs text-gray-500"><?= date('F d, Y', strtotime($patient['created_at'])) ?></p>
                        </div>
                        
                        <?php 
                        $first_appointment = $appointments ? end($appointments) : null;
                        if ($first_appointment): 
                        ?>
                        <div class="timeline-item">
                            <p class="text-sm font-medium text-gray-800">First appointment</p>
                            <p class="text-xs text-gray-500"><?= date('F d, Y', strtotime($first_appointment['appointment_date'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $latest_appointment = $appointments ? $appointments[0] : null;
                        if ($latest_appointment): 
                        ?>
                        <div class="timeline-item">
                            <p class="text-sm font-medium text-gray-800">Most recent visit</p>
                            <p class="text-xs text-gray-500"><?= date('F d, Y', strtotime($latest_appointment['appointment_date'])) ?></p>
                            <p class="text-xs text-gray-600 mt-1">Status: <?= ucfirst($latest_appointment['status']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($patient['upcoming_appointments'] > 0): ?>
                        <div class="timeline-item">
                            <p class="text-sm font-medium text-green-600">Upcoming appointment</p>
                            <p class="text-xs text-gray-500">Scheduled</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

</body>
</html>