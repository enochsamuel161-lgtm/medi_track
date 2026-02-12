<?php
// dashboard/admin/index.php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Get counts from database
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
$total_appointments = $stmt->fetchColumn();

// Get today's appointments
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE DATE(ds.slot_time) = CURDATE()
");
$stmt->execute();
$today_appointments = $stmt->fetchColumn();

// Get pending appointments
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending' OR status IS NULL");
$pending_appointments = $stmt->fetchColumn();

// Get recent appointments for activity feed
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.status,
        a.created_at,
        u.fullname AS patient_name,
        d.fullname AS doctor_name,
        ds.slot_time
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN doctor_slots ds ON a.slot_id = ds.id
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_appointments = $stmt->fetchAll();

// Get doctor categories count
$stmt = $pdo->query("SELECT COUNT(*) FROM doctor_categories");
$total_categories = $stmt->fetchColumn();

// Get admin name
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard | MediTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same style as doctors.php, patients.php, appointments.php -->
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
            <a href="index.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="doctors.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
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
                <span class="ml-auto bg-blue-600 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_categories ?></span>
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
        
        <!-- Header with Date -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                    <p class="text-sm text-gray-600 mt-1">
                        Welcome back, <span class="font-semibold text-blue-600"><?= htmlspecialchars($admin_name ?: 'Admin') ?></span>!
                    </p>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                        <span class="text-gray-700 font-medium"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATS CARDS - Improved mobile grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            
            <!-- Total Doctors Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-b-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Doctors</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_doctors) ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Active medical staff</span>
                    <a href="doctors.php" class="text-xs font-medium text-blue-600 hover:text-blue-800 flex items-center">
                        View all <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Total Patients Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-b-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Patients</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_patients) ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-users text-green-600 text-2xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Registered patients</span>
                    <a href="patients.php" class="text-xs font-medium text-green-600 hover:text-green-800 flex items-center">
                        View all <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Total Appointments Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-b-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Appointments</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_appointments) ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-calendar-check text-purple-600 text-2xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-500">All time</span>
                    <a href="appointments.php" class="text-xs font-medium text-purple-600 hover:text-purple-800 flex items-center">
                        View all <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Today's Appointments Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all border-b-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Today</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($today_appointments) ?></p>
                    </div>
                    <div class="bg-orange-100 p-3 rounded-lg">
                        <i class="fas fa-clock text-orange-600 text-2xl"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-500"><?= date('M j, Y') ?></span>
                    <a href="appointments.php?date_filter=<?= date('Y-m-d') ?>" class="text-xs font-medium text-orange-600 hover:text-orange-800 flex items-center">
                        View schedule <i class="fas fa-arrow-right ml-1 text-xs"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- SECOND ROW STATS -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            
            <!-- Pending Appointments Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-hourglass-half text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Pending Appointments</p>
                            <p class="text-3xl font-bold text-yellow-600"><?= number_format($pending_appointments) ?></p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Awaiting confirmation</span>
                    <a href="appointments.php?status_filter=pending" class="text-sm bg-yellow-50 hover:bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg transition-colors">
                        Review <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Categories Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-lg mr-4">
                            <i class="fas fa-tags text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Doctor Categories</p>
                            <p class="text-3xl font-bold text-blue-600"><?= number_format($total_categories) ?></p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-500">Specializations</span>
                    <a href="categories.php" class="text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2 rounded-lg transition-colors">
                        Manage <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Actions Card - Mobile optimized -->
            <div class="bg-white p-6 rounded-xl shadow-md md:col-span-2 lg:col-span-1">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-3">
                    <a href="add_doctor.php" class="flex items-center p-3 bg-gradient-to-r from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 rounded-lg transition-all group">
                        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-blue-700">Add New Doctor</span>
                        <i class="fas fa-arrow-right ml-auto text-blue-500 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                    
                    <a href="categories.php" class="flex items-center p-3 bg-gradient-to-r from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 rounded-lg transition-all group">
                        <div class="w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-tags"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-green-700">Add New Category</span>
                        <i class="fas fa-arrow-right ml-auto text-green-500 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                    
                    <a href="appointments.php" class="flex items-center p-3 bg-gradient-to-r from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 rounded-lg transition-all group">
                        <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center text-white group-hover:scale-110 transition-transform">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <span class="ml-3 font-medium text-gray-700 group-hover:text-purple-700">Manage Appointments</span>
                        <i class="fas fa-arrow-right ml-auto text-purple-500 group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- RECENT ACTIVITY & SYSTEM INFO -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <!-- Recent Appointments -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden lg:col-span-2">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-history text-blue-600 mr-2"></i>
                        Recent Appointments
                    </h3>
                    <a href="appointments.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                        View all <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="divide-y divide-gray-100">
                    <?php if (empty($recent_appointments)): ?>
                        <div class="p-6 text-center">
                            <i class="fas fa-calendar-check text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500">No recent appointments</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_appointments as $apt): ?>
                            <div class="p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                            <?= strtoupper(substr($apt['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">
                                                <?= htmlspecialchars($apt['patient_name']) ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                with Dr. <?= htmlspecialchars($apt['doctor_name']) ?>
                                            </p>
                                            <p class="text-xs text-gray-400 mt-1">
                                                <i class="far fa-clock mr-1"></i>
                                                <?= date('M d, Y h:i A', strtotime($apt['slot_time'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'completed' => 'bg-blue-100 text-blue-800'
                                        ];
                                        $status_color = $status_colors[$apt['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $status_color ?>">
                                            <?= ucfirst($apt['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Info & Welcome -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center">
                            <i class="fas fa-hospital text-blue-600 text-4xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-white text-center">MediTrack HMS</h3>
                    <p class="text-blue-100 text-center text-sm mt-1">Hospital Management System</p>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">System Version</span>
                            <span class="text-sm font-semibold text-gray-800">v2.0.0</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Last Login</span>
                            <span class="text-sm text-gray-800"><?= date('M d, Y h:i A') ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Admin Since</span>
                            <span class="text-sm text-gray-800">2024</span>
                        </div>
                        <div class="pt-4 mt-2 border-t border-gray-100">
                            <div class="bg-blue-50 rounded-lg p-3">
                                <p class="text-xs text-blue-700">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    You have <?= $pending_appointments ?> pending appointments to review.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MOTIVATIONAL QUOTE -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-6 rounded-xl shadow-lg text-white">
            <div class="flex items-center">
                <i class="fas fa-quote-left text-3xl opacity-50 mr-4"></i>
                <div>
                    <p class="text-lg font-medium">"The best way to find yourself is to lose yourself in the service of others."</p>
                    <p class="text-sm text-indigo-100 mt-2">— Mahatma Gandhi</p>
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

    // Auto refresh pending count? Optional
    // setTimeout(() => location.reload(), 300000); // Refresh every 5 minutes
</script>

</body>
</html>