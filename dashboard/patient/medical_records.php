<?php
session_start();
require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];

// Get patient details for sidebar
$stmt = $pdo->prepare("SELECT fullname, email, phone, created_at, date_of_birth, blood_group, allergies FROM users WHERE id = ?");
$stmt->execute([$patient_id]);
$user = $stmt->fetch();

$fullname = $user ? $user['fullname'] : 'Patient';
$email = $user ? $user['email'] : '';
$phone = $user ? ($user['phone'] ?? 'Not provided') : 'Not provided';
$member_since = $user ? date('F Y', strtotime($user['created_at'])) : date('F Y');
$date_of_birth = $user ? $user['date_of_birth'] : null;
$blood_group = $user ? ($user['blood_group'] ?? 'Not specified') : 'Not specified';
$allergies = $user ? ($user['allergies'] ?? 'None reported') : 'None reported';

// Calculate age if DOB exists
$age = null;
if ($date_of_birth) {
    $dob = new DateTime($date_of_birth);
    $now = new DateTime();
    $age = $dob->diff($now)->y;
}

// Get initials for avatar
$name_parts = explode(' ', $fullname);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($fullname, 0, 2));
}

// Fetch appointment history with doctor details
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.status,
        a.created_at as booked_at,
        ds.slot_time as appointment_date,
        d.id as doctor_id,
        d.fullname as doctor_name,
        d.specialty,
        dc.category_name,
        dc.id as category_id,
        (SELECT rating FROM doctor_ratings WHERE doctor_id = d.id AND patient_id = ? AND appointment_id = a.id) as given_rating
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN doctor_categories dc ON d.category_id = dc.id
    WHERE a.patient_id = ? AND ds.slot_time < NOW() AND a.status = 'approved'
    ORDER BY ds.slot_time DESC
");
$stmt->execute([$patient_id, $patient_id]);
$appointment_history = $stmt->fetchAll();

// Fetch prescriptions (you'll need to create this table)
$prescriptions = [];
// $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC");
// $stmt->execute([$patient_id]);
// $prescriptions = $stmt->fetchAll();

// Fetch lab reports (you'll need to create this table)
$lab_reports = [];
// $stmt = $pdo->prepare("SELECT * FROM lab_reports WHERE patient_id = ? ORDER BY test_date DESC");
// $stmt->execute([$patient_id]);
// $lab_reports = $stmt->fetchAll();

// Get upcoming appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status IN ('pending', 'approved')
");
$stmt->execute([$patient_id]);
$upcoming_count = $stmt->fetchColumn();

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Medical Records | MediTrack</title>
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
        .record-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .tab-active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
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
            
            <!-- Patient Quick Health Info -->
            <div class="mt-6 bg-blue-700/30 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
                    <i class="fas fa-heartbeat mr-2"></i>
                    Health Profile
                </h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <p class="text-blue-200">Blood Type</p>
                        <p class="text-white font-bold"><?= htmlspecialchars($blood_group) ?></p>
                    </div>
                    <div>
                        <p class="text-blue-200">Age</p>
                        <p class="text-white font-bold"><?= $age ? $age . ' years' : 'Not specified' ?></p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-blue-200">Allergies</p>
                        <p class="text-white font-medium truncate"><?= htmlspecialchars($allergies) ?></p>
                    </div>
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
            <a href="medical_records.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
                        <span class="text-gray-700 font-medium">Medical Records</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-notes-medical text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Medical Records
                            </h1>
                            <p class="text-gray-600 mt-1">
                                View your complete health history and records
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 rounded-lg transition-colors shadow-sm border border-gray-200">
                        <i class="fas fa-download mr-2 text-blue-600"></i>
                        Export
                    </button>
                    <a href="categories.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-calendar-plus mr-2"></i>
                        New Appointment
                    </a>
                </div>
            </div>
        </div>

        <!-- Health Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Visits</p>
                        <p class="text-2xl font-bold text-gray-800"><?= count($appointment_history) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Lifetime appointments</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Prescriptions</p>
                        <p class="text-2xl font-bold text-green-600"><?= count($prescriptions) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-prescription text-green-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Active & past</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Lab Reports</p>
                        <p class="text-2xl font-bold text-purple-600"><?= count($lab_reports) ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-flask text-purple-600"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Tests & results</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Last Visit</p>
                        <p class="text-lg font-bold text-yellow-600">
                            <?php 
                            if (count($appointment_history) > 0) {
                                echo date('M d, Y', strtotime($appointment_history[0]['appointment_date']));
                            } else {
                                echo 'No visits';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Records Tabs -->
        <div class="bg-white rounded-xl shadow-md mb-6">
            <div class="border-b border-gray-200">
                <div class="flex flex-wrap px-2">
                    <a href="?filter=all" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'all' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-history mr-2"></i>
                        Visit History
                    </a>
                    <a href="?filter=prescriptions" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'prescriptions' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-prescription mr-2"></i>
                        Prescriptions
                    </a>
                    <a href="?filter=labs" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'labs' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-flask mr-2"></i>
                        Lab Reports
                    </a>
                    <a href="?filter=vitals" 
                       class="px-6 py-4 text-sm font-medium <?= $filter === 'vitals' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' ?>">
                        <i class="fas fa-heartbeat mr-2"></i>
                        Vitals
                    </a>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div class="p-4">
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" 
                           placeholder="Search medical records, doctors, or dates..." 
                           value="<?= htmlspecialchars($search) ?>"
                           class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                </div>
            </div>
        </div>

        <!-- Records Content -->
        <div class="space-y-6">
            
            <!-- VISIT HISTORY SECTION -->
            <?php if ($filter === 'all'): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <i class="fas fa-history text-blue-600"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white ml-3">Visit History</h3>
                            </div>
                            <span class="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
                                <?= count($appointment_history) ?> visits
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php if (count($appointment_history) > 0): ?>
                            <div class="space-y-4">
                                <?php foreach ($appointment_history as $index => $visit): ?>
                                    <div class="record-card border border-gray-200 rounded-xl p-5 hover:border-blue-300 hover:shadow-md transition-all">
                                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                                            <!-- Left: Date -->
                                            <div class="flex items-start mb-3 lg:mb-0">
                                                <div class="text-center min-w-[70px]">
                                                    <div class="text-2xl font-bold text-gray-800">
                                                        <?= date('d', strtotime($visit['appointment_date'])) ?>
                                                    </div>
                                                    <div class="text-sm font-semibold text-gray-600">
                                                        <?= date('M', strtotime($visit['appointment_date'])) ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?= date('Y', strtotime($visit['appointment_date'])) ?>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock text-blue-500 mr-2 text-sm"></i>
                                                        <span class="text-sm font-medium text-gray-700">
                                                            <?= date('h:i A', strtotime($visit['appointment_date'])) ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center mt-1">
                                                        <i class="fas fa-tag text-gray-400 mr-2 text-xs"></i>
                                                        <span class="text-xs text-gray-600">
                                                            <?= htmlspecialchars($visit['category_name']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Middle: Doctor -->
                                            <div class="flex-1 lg:ml-6 mb-3 lg:mb-0">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                                        <?= strtoupper(substr($visit['doctor_name'], 0, 1)) ?>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="font-semibold text-gray-800">
                                                            Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-600">
                                                            <?= htmlspecialchars($visit['specialty']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Right: Actions -->
                                            <div class="flex flex-col items-start lg:items-end">
                                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                                    Completed
                                                </span>
                                                <div class="mt-3 flex space-x-2">
                                                    <a href="visit_summary.php?id=<?= $visit['id'] ?>" 
                                                       class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-medium transition-colors">
                                                        <i class="fas fa-file-medical mr-1"></i>
                                                        Summary
                                                    </a>
                                                    <?php if (!$visit['given_rating']): ?>
                                                        <a href="rate_doctor.php?doctor_id=<?= $visit['doctor_id'] ?>&appointment_id=<?= $visit['id'] ?>" 
                                                           class="px-3 py-1.5 bg-yellow-50 hover:bg-yellow-100 text-yellow-700 rounded-lg text-xs font-medium transition-colors">
                                                            <i class="fas fa-star mr-1"></i>
                                                            Rate
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Visit Notes (Placeholder) -->
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <div class="flex items-start">
                                                <i class="fas fa-notes-medical text-gray-400 mt-1 mr-2 text-xs"></i>
                                                <p class="text-xs text-gray-600">
                                                    <span class="font-medium">Consultation notes:</span> 
                                                    Regular checkup - Patient showed improvement. Prescribed medication for 2 weeks. Follow-up recommended in 3 months.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Empty Visit History -->
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-calendar-times text-gray-400 text-3xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-2">No Visit History</h4>
                                <p class="text-gray-500 text-sm max-w-md mx-auto">
                                    You haven't had any medical visits yet. Book your first appointment to start your health journey.
                                </p>
                                <a href="categories.php" class="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    <i class="fas fa-calendar-plus mr-2"></i>
                                    Book Appointment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- PRESCRIPTIONS SECTION -->
            <?php if ($filter === 'prescriptions'): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <i class="fas fa-prescription text-green-600 text-xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white ml-3">Prescriptions</h3>
                            </div>
                            <span class="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
                                <?= count($prescriptions) ?> prescriptions
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php if (count($prescriptions) > 0): ?>
                            <!-- Prescriptions will be displayed here -->
                            <div class="space-y-4">
                                <!-- Sample prescription card - replace with actual data -->
                                <div class="border border-gray-200 rounded-xl p-5">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Amoxicillin 500mg</h4>
                                            <p class="text-sm text-gray-600 mt-1">Dr. Sarah Johnson • Cardiology</p>
                                            <p class="text-xs text-gray-500 mt-2">Prescribed: Jan 15, 2024</p>
                                        </div>
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                            Active
                                        </span>
                                    </div>
                                    <div class="mt-3 flex items-center text-sm">
                                        <i class="fas fa-clock text-gray-400 mr-2"></i>
                                        <span class="text-gray-600">Take 1 capsule twice daily after meals</span>
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
                                        <span class="text-xs text-gray-500">7 days supply • 2 refills remaining</span>
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-download mr-1"></i> Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Empty Prescriptions -->
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-prescription text-gray-400 text-3xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-2">No Prescriptions</h4>
                                <p class="text-gray-500 text-sm max-w-md mx-auto">
                                    You don't have any active prescriptions. Visit a doctor to get prescribed medication.
                                </p>
                                <a href="doctors.php" class="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                                    <i class="fas fa-user-md mr-2"></i>
                                    Find a Doctor
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- LAB REPORTS SECTION -->
            <?php if ($filter === 'labs'): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <i class="fas fa-flask text-purple-600 text-xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white ml-3">Lab Reports</h3>
                            </div>
                            <span class="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
                                <?= count($lab_reports) ?> reports
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php if (count($lab_reports) > 0): ?>
                            <!-- Lab reports will be displayed here -->
                            <div class="space-y-4">
                                <!-- Sample lab report card - replace with actual data -->
                                <div class="border border-gray-200 rounded-xl p-5">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-semibold text-gray-800">Complete Blood Count (CBC)</h4>
                                            <p class="text-sm text-gray-600 mt-1">Pathology Lab • Dr. Michael Chen</p>
                                            <div class="flex items-center mt-2">
                                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                                    Normal Range
                                                </span>
                                                <span class="text-xs text-gray-500 ml-3">
                                                    Tested: Feb 10, 2024
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
                                        <span class="text-xs text-gray-500">Report ID: LAB-2024-0210-001</span>
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-file-pdf mr-1"></i> View Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Empty Lab Reports -->
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-flask text-gray-400 text-3xl"></i>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-800 mb-2">No Lab Reports</h4>
                                <p class="text-gray-500 text-sm max-w-md mx-auto">
                                    You don't have any lab reports yet. Your test results will appear here.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- VITALS SECTION -->
            <?php if ($filter === 'vitals'): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-yellow-600 to-yellow-700 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                                    <i class="fas fa-heartbeat text-yellow-600 text-xl"></i>
                                </div>
                                <h3 class="text-xl font-semibold text-white ml-3">Vital Signs</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Blood Pressure -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Blood Pressure</h4>
                                    <i class="fas fa-heartbeat text-red-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">120/80</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-green-600 mt-2">✓ Normal range</p>
                            </div>
                            
                            <!-- Heart Rate -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Heart Rate</h4>
                                    <i class="fas fa-heart text-red-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">72 bpm</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-green-600 mt-2">✓ Normal range</p>
                            </div>
                            
                            <!-- Blood Sugar -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Blood Sugar</h4>
                                    <i class="fas fa-droplet text-red-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">95 mg/dL</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-green-600 mt-2">✓ Fasting normal</p>
                            </div>
                            
                            <!-- Temperature -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Temperature</h4>
                                    <i class="fas fa-thermometer-half text-orange-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">98.6 °F</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-green-600 mt-2">✓ Normal</p>
                            </div>
                            
                            <!-- Weight -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Weight</h4>
                                    <i class="fas fa-weight text-blue-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">70 kg</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-blue-600 mt-2">BMI: 22.5</p>
                            </div>
                            
                            <!-- Oxygen -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-gray-700">Oxygen Level</h4>
                                    <i class="fas fa-lungs text-blue-500"></i>
                                </div>
                                <p class="text-2xl font-bold text-gray-800">98%</p>
                                <p class="text-xs text-gray-500 mt-1">Last measured: Feb 10, 2024</p>
                                <p class="text-xs text-green-600 mt-2">✓ Normal</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 text-center">
                            <p class="text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Vitals are recorded during your visits. Track your health over time.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Health Timeline (Conditional) -->
        <?php if ($filter === 'all' && count($appointment_history) > 0): ?>
        <div class="mt-8 bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                Health Timeline
            </h3>
            <div class="relative">
                <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-blue-200"></div>
                <div class="space-y-6">
                    <?php 
                    $timeline_visits = array_slice($appointment_history, 0, 5);
                    foreach ($timeline_visits as $visit): 
                    ?>
                    <div class="relative pl-10">
                        <div class="absolute left-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center border-4 border-white">
                            <i class="fas fa-calendar-check text-blue-600 text-xs"></i>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="font-semibold text-gray-800">
                                <?= date('F d, Y', strtotime($visit['appointment_date'])) ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                Visit with Dr. <?= htmlspecialchars($visit['doctor_name']) ?> • <?= htmlspecialchars($visit['specialty']) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Request Records Section -->
        <div class="mt-8 bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-md p-6 text-white">
            <div class="flex flex-col sm:flex-row items-center justify-between">
                <div class="flex items-center mb-4 sm:mb-0">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-export text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold">Need your complete medical records?</h4>
                        <p class="text-blue-100 text-sm">Request your full health record for personal use or transfer</p>
                    </div>
                </div>
                <button class="px-6 py-3 bg-white hover:bg-gray-100 text-blue-700 font-semibold rounded-lg transition-colors shadow-lg">
                    <i class="fas fa-download mr-2"></i>
                    Request Records
                </button>
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
    
    // Search functionality
    const searchInput = document.querySelector('input[placeholder*="Search medical records"]');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value;
                window.location.href = '?filter=<?= $filter ?>&search=' + encodeURIComponent(searchTerm);
            }
        });
    }
</script>

</body>
</html>