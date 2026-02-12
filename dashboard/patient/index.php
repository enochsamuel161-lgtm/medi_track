<?php
session_start();

// Block access if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit;
}

// Disable browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require "../../config/database.php";


// Fetch patient details - INCLUDING PHONE
$stmt = $pdo->prepare("SELECT fullname, email, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$fullname = $user ? $user['fullname'] : 'Patient';
$email = $user ? $user['email'] : '';
$phone = $user ? ($user['phone'] ?? 'Not provided') : 'Not provided'; // FIXED: Added phone variable
$member_since = $user ? date('F Y', strtotime($user['created_at'])) : date('F Y');

// Fetch upcoming appointments
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.status,
        ds.slot_time,
        d.fullname as doctor_name,
        d.specialty,
        dc.category_name
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN doctor_categories dc ON d.category_id = dc.id
    WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status IN ('pending', 'approved')
    ORDER BY ds.slot_time ASC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetchAll();

// Fetch past appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time < NOW()
");
$stmt->execute([$_SESSION['user_id']]);
$past_appointments_count = $stmt->fetchColumn();

// Fetch total appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments WHERE patient_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$total_appointments = $stmt->fetchColumn();

// Get initials for avatar
$name_parts = explode(' ', $fullname);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($fullname, 0, 2));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Patient Dashboard | MediTrack</title>
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
        .gradient-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Enhanced for patient -->
    <aside class="lg:w-80 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
        <!-- Profile Section - Prominent on patient dashboard -->
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
                    <!-- ADDED: Phone number display -->
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
            <div class="mt-6 grid grid-cols-2 gap-3 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $total_appointments ?></p>
                    <p class="text-xs text-blue-200">Total Visits</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $past_appointments_count ?></p>
                    <p class="text-xs text-blue-200">Completed</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="book_appointment.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-plus w-5 h-5 mr-3"></i>
                <span>Book Appointment</span>
            </a>
            <a href="my_appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>My Appointments</span>
                <?php if (count($upcoming_appointments) > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= count($upcoming_appointments) ?>
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
                <span class="ml-auto bg-green-500 text-white px-2 py-0.5 rounded-full text-xs">3</span>
            </a>
            <a href="profile.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-user-circle w-5 h-5 mr-3"></i>
                <span>Profile</span>
            </a>
            <a href="settings.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-cog w-5 h-5 mr-3"></i>
                <span>Settings</span>
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
        
        <!-- Welcome Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 flex items-center">
                    👋 Welcome back, 
                    <span class="text-blue-600 ml-2"><?= htmlspecialchars(explode(' ', $fullname)[0]) ?>!</span>
                </h1>
                <p class="text-gray-600 mt-2 flex items-center">
                    <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                    <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="book_appointment.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl shadow-lg transition-all transform hover:scale-105">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Book New Appointment
                </a>
            </div>
        </div>

        <!-- Quick Actions Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="book_appointment.php" class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-all text-center group">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-600 transition-colors">
                    <i class="fas fa-calendar-plus text-blue-600 text-xl group-hover:text-white"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Book</h3>
                <p class="text-xs text-gray-500">Appointment</p>
            </a>
            <a href="doctors.php" class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-all text-center group">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-green-600 transition-colors">
                    <i class="fas fa-user-md text-green-600 text-xl group-hover:text-white"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Find</h3>
                <p class="text-xs text-gray-500">Doctors</p>
            </a>
            <a href="appointments.php" class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-all text-center group">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-600 transition-colors">
                    <i class="fas fa-clock text-purple-600 text-xl group-hover:text-white"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Upcoming</h3>
                <p class="text-xs text-gray-500">Appointments</p>
                <?php if (count($upcoming_appointments) > 0): ?>
                    <span class="absolute -mt-12 ml-8 bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold">
                        <?= count($upcoming_appointments) ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="medical_records.php" class="bg-white p-5 rounded-xl shadow-md hover:shadow-lg transition-all text-center group">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-yellow-600 transition-colors">
                    <i class="fas fa-file-medical text-yellow-600 text-xl group-hover:text-white"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Medical</h3>
                <p class="text-xs text-gray-500">Records</p>
            </a>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Upcoming Appointments Card -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-white ml-3">Upcoming Appointments</h3>
                    </div>
                    <a href="appointments.php" class="text-white hover:text-blue-100 text-sm flex items-center">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="p-6">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="text-center py-8">
                            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-calendar text-gray-400 text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-700 mb-2">No upcoming appointments</h4>
                            <p class="text-gray-500 mb-4">Ready to schedule your next visit?</p>
                            <a href="book_appointment.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Book Now
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_appointments as $apt): ?>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                                    <div class="flex items-start sm:items-center mb-3 sm:mb-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr($apt['doctor_name'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-semibold text-gray-800">Dr. <?= htmlspecialchars($apt['doctor_name']) ?></p>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($apt['specialty']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($apt['category_name']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto">
                                        <div class="mr-4">
                                            <p class="text-sm font-semibold text-gray-800">
                                                <?= date('M d, Y', strtotime($apt['slot_time'])) ?>
                                            </p>
                                            <p class="text-xs text-gray-600">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= date('h:i A', strtotime($apt['slot_time'])) ?>
                                            </p>
                                        </div>
                                        <?php if ($apt['status'] === 'pending'): ?>
                                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                                Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                                Confirmed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Sidebar - Health Tips & Quick Info -->
            <div class="space-y-6">
                
                <!-- Health Tip Card -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-md p-6 text-white">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-lightbulb text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold ml-3">Health Tip</h3>
                    </div>
                    <p class="text-sm leading-relaxed opacity-90">
                        "Stay hydrated! Drinking enough water helps maintain your body's fluid balance, regulates temperature, and keeps joints lubricated."
                    </p>
                    <div class="mt-4 pt-4 border-t border-white/20">
                        <p class="text-xs opacity-75">
                            <i class="fas fa-info-circle mr-1"></i>
                            Source: World Health Organization
                        </p>
                    </div>
                </div>

                <!-- Quick Contact Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-headset text-blue-600 mr-2"></i>
                        Need Help?
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Our support team is available 24/7 to assist you with appointments and inquiries.
                    </p>
                    <div class="space-y-3">
                        <a href="tel:+1234567890" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-phone-alt text-blue-600 text-sm"></i>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700">+1 (234) 567-890</span>
                        </a>
                        <a href="mailto:support@meditrack.com" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-envelope text-green-600 text-sm"></i>
                            </div>
                            <span class="ml-3 text-sm font-medium text-gray-700">support@meditrack.com</span>
                        </a>
                    </div>
                </div>

                <!-- Quick Stats Card -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-chart-pie text-blue-600 mr-2"></i>
                        Your Health Stats
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-3 bg-blue-50 rounded-xl">
                            <p class="text-2xl font-bold text-blue-600"><?= $total_appointments ?></p>
                            <p class="text-xs text-gray-600">Total Visits</p>
                        </div>
                        <div class="text-center p-3 bg-green-50 rounded-xl">
                            <p class="text-2xl font-bold text-green-600"><?= $past_appointments_count ?></p>
                            <p class="text-xs text-gray-600">Completed</p>
                        </div>
                        <div class="text-center p-3 bg-purple-50 rounded-xl">
                            <p class="text-2xl font-bold text-purple-600"><?= count($upcoming_appointments) ?></p>
                            <p class="text-xs text-gray-600">Upcoming</p>
                        </div>
                        <div class="text-center p-3 bg-yellow-50 rounded-xl">
                            <p class="text-2xl font-bold text-yellow-600">0</p>
                            <p class="text-xs text-gray-600">Prescriptions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity / Medical Records Preview -->
        <div class="mt-6 bg-white rounded-2xl shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-history text-gray-800 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white ml-3">Recent Medical Records</h3>
                </div>
                <a href="medical_records.php" class="text-white hover:text-gray-200 text-sm flex items-center">
                    View all <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="p-6">
                <div class="text-center py-6">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-file-medical text-gray-400 text-2xl"></i>
                    </div>
                    <p class="text-gray-500">No recent medical records found</p>
                    <p class="text-xs text-gray-400 mt-1">Your medical history will appear here</p>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Mobile Menu Script -->
<script>
    // For future mobile menu implementation if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide any alert messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });
</script>

</body>
</html>