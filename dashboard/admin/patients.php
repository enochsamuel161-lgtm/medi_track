<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch patients with additional stats - REMOVED phone column
$stmt = $pdo->query("
    SELECT id, fullname, email, created_at, 
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = u.id) as total_appointments,
           (SELECT MAX(slot_time) FROM appointments a 
            JOIN doctor_slots ds ON a.slot_id = ds.id 
            WHERE a.patient_id = u.id) as last_visit
    FROM users u 
    WHERE role = 'patient'
    ORDER BY created_at DESC
");
$patients = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM users WHERE role = 'patient' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$new_patients_month = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(DISTINCT patient_id) FROM appointments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$active_patients_week = $stmt->fetchColumn();

// Get admin info for sidebar
$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_name = $stmt->fetchColumn();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending' OR status IS NULL");
$pending_appointments_sidebar = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Patients Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same style as doctors.php -->
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
            <a href="doctors.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-user-md w-5 h-5 mr-3"></i>
                <span>Doctors</span>
                <span class="ml-auto bg-blue-600 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_doctors_sidebar ?></span>
            </a>
            <a href="patients.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>Patients</span>
                <span class="ml-auto bg-white text-blue-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_patients_sidebar ?></span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if($pending_appointments_sidebar > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $pending_appointments_sidebar ?></span>
                <?php endif; ?>
            </a>
            <a href="categories.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-tags w-5 h-5 mr-3"></i>
                <span>Categories</span>
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
        
        <!-- Header with Stats -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Patients Management</h1>
                    <p class="text-sm text-gray-600 mt-1">View and manage all registered patients</p>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-user-plus mr-2"></i><?= $new_patients_month ?> new this month
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <!-- Total Patients Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Patients</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_patients) ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- New Patients Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">New This Month</p>
                        <p class="text-3xl font-bold text-green-600"><?= number_format($new_patients_month) ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-user-plus text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Active Patients Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Active This Week</p>
                        <p class="text-3xl font-bold text-purple-600"><?= number_format($active_patients_week) ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-heartbeat text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" placeholder="Search patients by name or email..." 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
            </div>
        </div>

        <!-- Patients List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Registered</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Visit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Appointments</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="patientTableBody">
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                                        <p class="text-gray-500 text-lg">No patients found</p>
                                        <p class="text-gray-400 text-sm mt-2">Patients will appear here after registration</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr class="hover:bg-gray-50 transition-colors patient-row" 
                                    data-name="<?= strtolower($patient['fullname']) ?>" 
                                    data-email="<?= strtolower($patient['email']) ?>"
                                    data-date="<?= $patient['created_at'] ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($patient['fullname']) ?></p>
                                                <p class="text-xs text-gray-500">ID: PAT-<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars($patient['email']) ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?= date('M d, Y', strtotime($patient['created_at'])) ?></p>
                                        <p class="text-xs text-gray-500"><?= date('h:i A', strtotime($patient['created_at'])) ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($patient['last_visit']): ?>
                                            <p class="text-sm text-gray-900"><?= date('M d, Y', strtotime($patient['last_visit'])) ?></p>
                                            <p class="text-xs text-gray-500"><?= date('h:i A', strtotime($patient['last_visit'])) ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">No visits yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-blue-600"><?= $patient['total_appointments'] ?? 0 ?></span>
                                            <span class="text-xs text-gray-500 ml-1">total</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <a href="view_patient.php?id=<?= $patient['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800 transition-colors"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="patient_history.php?id=<?= $patient['id'] ?>" 
                                               class="text-purple-600 hover:text-purple-800 transition-colors"
                                               title="Medical History">
                                                <i class="fas fa-notes-medical"></i>
                                            </a>
                                            <a href="mailto:<?= $patient['email'] ?>" 
                                               class="text-green-600 hover:text-green-800 transition-colors"
                                               title="Send Email">
                                                <i class="fas fa-envelope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden space-y-4 p-4">
                <?php if (empty($patients)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No patients found</p>
                        <p class="text-gray-400 text-sm">Patients will appear here after registration</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm patient-mobile-card"
                             data-name="<?= strtolower($patient['fullname']) ?>">
                            <!-- Patient Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($patient['fullname']) ?></h3>
                                        <p class="text-xs text-gray-500">PAT-<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium">
                                    <?= $patient['total_appointments'] ?? 0 ?> appts
                                </span>
                            </div>
                            
                            <!-- Patient Details Grid -->
                            <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Email</p>
                                    <p class="font-medium truncate"><?= htmlspecialchars($patient['email']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Registered</p>
                                    <p class="font-medium"><?= date('M d, Y', strtotime($patient['created_at'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Last Visit</p>
                                    <p class="font-medium"><?= $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'Never' ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Patient ID</p>
                                    <p class="font-medium">PAT-<?= str_pad($patient['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                                <div class="flex space-x-4">
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>" class="text-blue-600 hover:text-blue-800 p-2">
                                        <i class="fas fa-eye"></i>
                                        <span class="text-xs ml-1">View</span>
                                    </a>
                                    <a href="patient_history.php?id=<?= $patient['id'] ?>" class="text-purple-600 hover:text-purple-800 p-2">
                                        <i class="fas fa-notes-medical"></i>
                                        <span class="text-xs ml-1">History</span>
                                    </a>
                                    <a href="mailto:<?= $patient['email'] ?>" class="text-green-600 hover:text-green-800 p-2">
                                        <i class="fas fa-envelope"></i>
                                        <span class="text-xs ml-1">Email</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript for Mobile Menu and Search -->
<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }

    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        
        function filterPatients() {
            const searchTerm = searchInput.value.toLowerCase();
            
            // Filter desktop rows
            const rows = document.querySelectorAll('.patient-row');
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                
                const matchesSearch = searchTerm === '' || 
                    name.includes(searchTerm) || 
                    email.includes(searchTerm);
                
                row.style.display = matchesSearch ? '' : 'none';
            });
            
            // Filter mobile cards
            const mobileCards = document.querySelectorAll('.patient-mobile-card');
            mobileCards.forEach(card => {
                const name = card.dataset.name || '';
                const matchesSearch = searchTerm === '' || name.includes(searchTerm);
                card.style.display = matchesSearch ? '' : 'none';
            });
        }
        
        searchInput.addEventListener('input', filterPatients);
    });
</script>

</body>
</html>