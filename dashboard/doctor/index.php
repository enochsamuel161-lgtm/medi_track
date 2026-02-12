<?php
session_start();
require "../../config/database.php";

// Only allow logged-in doctors
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../../public/login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];

// Ensure doctor fullname is set
if (!isset($_SESSION['fullname']) || empty($_SESSION['fullname'])) {
    $stmt = $pdo->prepare("SELECT fullname, specialty, email, phone FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    $_SESSION['fullname'] = $doctor ? $doctor['fullname'] : "Doctor";
    $_SESSION['specialty'] = $doctor ? $doctor['specialty'] : "General";
    $_SESSION['email'] = $doctor ? $doctor['email'] : "";
    $_SESSION['phone'] = $doctor ? $doctor['phone'] : "";
}

// Get doctor statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        COUNT(CASE WHEN ds.slot_time >= NOW() AND a.status != 'cancelled' THEN 1 END) as upcoming,
        COUNT(CASE WHEN ds.slot_time < NOW() AND a.status = 'approved' THEN 1 END) as completed,
        COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ?
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch();

// Get total patients count
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT patient_id) as total_patients
    FROM appointments
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$patient_count = $stmt->fetchColumn();

// Get unread messages count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE doctor_id = ? AND status = 'sent'
");
$stmt->execute([$doctor_id]);
$unread_messages = $stmt->fetchColumn();

// Get today's appointments
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
    WHERE a.doctor_id = ? AND DATE(ds.slot_time) = CURDATE()
    ORDER BY ds.slot_time ASC
    LIMIT 5
");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetchAll();

// Get initials for avatar
$name_parts = explode(' ', $_SESSION['fullname']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($_SESSION['fullname'], 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Doctor Dashboard | MediTrack</title>
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
        .dashboard-card:hover {
            transform: translateY(-4px);
            transition: transform 0.3s ease;
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
                    <h2 class="text-xl font-bold truncate">Dr. <?= htmlspecialchars($_SESSION['fullname']) ?></h2>
                    <p class="text-sm text-blue-200 flex items-center mt-1">
                        <i class="fas fa-stethoscope mr-2 text-xs"></i>
                        <?= htmlspecialchars($_SESSION['specialty'] ?? 'General') ?>
                    </p>
                    <p class="text-xs text-blue-300 mt-2 flex items-center">
                        <i class="fas fa-envelope mr-2"></i>
                        <?= htmlspecialchars($_SESSION['email'] ?? 'Not provided') ?>
                    </p>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-2 gap-3 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $patient_count ?></p>
                    <p class="text-xs text-blue-200">Total Patients</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $stats['upcoming'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Upcoming</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if ($stats['pending'] > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $stats['pending'] ?>
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
            <a href="schedule.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
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
        
        <!-- Header with Welcome Message -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 flex items-center">
                        👋 Welcome back, 
                        <span class="text-blue-600 ml-2">Dr. <?= htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]) ?>!</span>
                    </h1>
                    <p class="text-gray-600 mt-2 flex items-center">
                        <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                        <?= date('l, F j, Y') ?>
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-id-card text-blue-600 mr-2"></i>
                        <span class="text-sm text-gray-600">ID: DOC-<?= str_pad($doctor_id, 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Appointments Card -->
            <div class="dashboard-card bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Appointments</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $stats['total_appointments'] ?? 0 ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">All time appointments</p>
            </div>

            <!-- Pending Appointments Card -->
            <div class="dashboard-card bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending'] ?? 0 ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Awaiting confirmation</p>
            </div>

            <!-- Today's Appointments Card -->
            <div class="dashboard-card bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Today</p>
                        <p class="text-3xl font-bold text-green-600"><?= count($today_appointments) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-green-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Scheduled for today</p>
            </div>

            <!-- Messages Card -->
            <div class="dashboard-card bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Messages</p>
                        <p class="text-3xl font-bold text-purple-600"><?= $unread_messages ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-envelope text-purple-600 text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Unread messages</p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Today's Schedule Card -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-day text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-white ml-3">Today's Schedule</h3>
                    </div>
                    <a href="appointments.php" class="text-white hover:text-blue-100 text-sm flex items-center">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="p-6">
                    <?php if (count($today_appointments) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($today_appointments as $apt): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr($apt['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($apt['patient_name']) ?></p>
                                            <p class="text-sm text-gray-600">Patient ID: PAT-<?= str_pad($apt['patient_id'], 6, '0', STR_PAD_LEFT) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="mr-4 text-right">
                                            <p class="text-sm font-semibold text-gray-800">
                                                <?= date('h:i A', strtotime($apt['slot_time'])) ?>
                                            </p>
                                            <span class="px-2 py-1 text-xs rounded-full <?= $apt['status'] == 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                <?= ucfirst($apt['status']) ?>
                                            </span>
                                        </div>
                                        <a href="appointment_details.php?id=<?= $apt['id'] ?>" class="text-blue-600 hover:text-blue-800 ml-2">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-calendar-check text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-600">No appointments scheduled for today</p>
                            <p class="text-sm text-gray-500 mt-1">Enjoy your day!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar - Quick Actions & Stats -->
            <div class="space-y-6">
                
                <!-- Quick Actions Card -->
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
                            <span class="ml-3 font-medium text-gray-700 group-hover:text-yellow-700">Review Pending</span>
                            <?php if ($stats['pending'] > 0): ?>
                                <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                                    <?= $stats['pending'] ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="messages.php" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-xl transition-colors group">
                            <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                                <i class="fas fa-comments"></i>
                            </div>
                            <span class="ml-3 font-medium text-gray-700 group-hover:text-green-700">Check Messages</span>
                            <?php if ($unread_messages > 0): ?>
                                <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                                    <?= $unread_messages ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
                
                <!-- Performance Stats Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-chart-pie text-blue-600 mr-2"></i>
                        Your Performance
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600">Completion Rate</span>
                                <span class="text-sm font-semibold text-gray-800">
                                    <?php 
                                    $completion_rate = ($stats['total_appointments'] > 0) 
                                        ? round(($stats['completed'] / $stats['total_appointments']) * 100) 
                                        : 0;
                                    echo $completion_rate . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 rounded-full h-2" style="width: <?= $completion_rate ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                            <span class="text-sm text-gray-600">Total Patients</span>
                            <span class="text-lg font-bold text-blue-600"><?= $patient_count ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Completed Appointments</span>
                            <span class="text-lg font-bold text-green-600"><?= $stats['completed'] ?? 0 ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending Reviews</span>
                            <span class="text-lg font-bold text-yellow-600"><?= $stats['pending'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Status Card -->
                <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-2xl shadow-md p-6 text-white">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-user-md text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold">Current Status</h4>
                            <p class="text-blue-100 text-sm">You're online</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-blue-100">Set your availability</span>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="availability-toggle" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Patients Section -->
        <div class="mt-8 bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-gray-800 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white ml-3">Recent Patients</h3>
                </div>
                <a href="patients.php" class="text-white hover:text-gray-200 text-sm flex items-center">
                    View all <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="p-6">
                <?php
                // Fetch recent unique patients
                $stmt = $pdo->prepare("
                    SELECT DISTINCT 
                        u.id,
                        u.fullname,
                        u.email,
                        MAX(ds.slot_time) as last_visit
                    FROM appointments a
                    JOIN doctor_slots ds ON a.slot_id = ds.id
                    JOIN users u ON a.patient_id = u.id
                    WHERE a.doctor_id = ?
                    GROUP BY u.id, u.fullname, u.email
                    ORDER BY last_visit DESC
                    LIMIT 5
                ");
                $stmt->execute([$doctor_id]);
                $recent_patients = $stmt->fetchAll();
                ?>
                
                <?php if (count($recent_patients) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <?php foreach ($recent_patients as $patient): ?>
                            <div class="bg-gray-50 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-xl mx-auto mb-3">
                                    <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                </div>
                                <h4 class="font-semibold text-gray-800 truncate"><?= htmlspecialchars($patient['fullname']) ?></h4>
                                <p class="text-xs text-gray-500 mt-1">
                                    Last visit: <?= date('M d, Y', strtotime($patient['last_visit'])) ?>
                                </p>
                                <a href="patient_details.php?id=<?= $patient['id'] ?>" class="mt-3 inline-block text-blue-600 hover:text-blue-800 text-sm">
                                    View Profile
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">No patients yet</p>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript for Availability Toggle -->
<script>
    document.getElementById('availability-toggle')?.addEventListener('change', function(e) {
        let status = this.checked ? 'available' : 'unavailable';
        
        // Update doctor status via AJAX
        fetch('ajax/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'status=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show toast notification
                alert('Status updated to ' + status);
            }
        })
        .catch(error => console.error('Error:', error));
    });
</script>

</body>
</html>