<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch doctors with more details and counts
$stmt = $pdo->query("
    SELECT d.id, d.fullname, d.email, d.phone, d.specialty, d.status, d.category_id,
           dc.category_name,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) as total_appointments,
           (SELECT COUNT(*) FROM doctor_slots ds WHERE ds.doctor_id = d.id AND ds.is_booked = 0) as available_slots
    FROM doctors d
    LEFT JOIN doctor_categories dc ON d.category_id = dc.id
    ORDER BY d.id DESC
");

$doctors = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM doctors WHERE status = 'available'");
$available_doctors = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM doctors WHERE status = 'busy'");
$busy_doctors = $stmt->fetchColumn();

// Get admin info
$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_name = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Doctors Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media (max-width: 640px) {
            .table-card {
                border-radius: 0.5rem;
            }
            .action-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Mobile Responsive -->
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
            
            <!-- Admin Info - Mobile Hidden Toggle -->
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

        <!-- Navigation Menu - Mobile Collapsible -->
        <nav id="navMenu" class="mt-6 px-4 space-y-1 hidden lg:block">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="doctors.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
                <i class="fas fa-user-md w-5 h-5 mr-3"></i>
                <span>Doctors</span>
                <span class="ml-auto bg-white text-blue-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_doctors ?></span>
            </a>
            <a href="patients.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>Patients</span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Doctors Management</h1>
                    <p class="text-sm text-gray-600 mt-1">Manage all doctors and their schedules</p>
                </div>
                <a href="add_doctor.php" class="mt-4 sm:mt-0 inline-flex items-center justify-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-lg shadow-lg transition-all transform hover:scale-105">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Add New Doctor
                </a>
            </div>
        </div>

        <!-- Statistics Cards - Mobile Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <!-- Total Doctors Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Doctors</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $total_doctors ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Available Doctors Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Available</p>
                        <p class="text-3xl font-bold text-green-600"><?= $available_doctors ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Busy Doctors Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Busy/Unavailable</p>
                        <p class="text-3xl font-bold text-red-600"><?= $busy_doctors ?></p>
                    </div>
                    <div class="bg-red-100 p-3 rounded-lg">
                        <i class="fas fa-clock text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar - Mobile Responsive -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" placeholder="Search doctors by name, email, or specialty..." 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
                <div class="sm:w-48">
                    <select id="statusFilter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All Status</option>
                        <option value="available">Available</option>
                        <option value="busy">Busy</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Doctors List - Mobile Responsive Card View -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Desktop Table View (hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Specialty</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Stats</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="doctorTableBody">
                        <?php if (empty($doctors)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-user-md text-gray-400 text-5xl mb-4"></i>
                                        <p class="text-gray-500 text-lg">No doctors found</p>
                                        <p class="text-gray-400 text-sm mt-2">Get started by adding your first doctor</p>
                                        <a href="add_doctor.php" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                                            <i class="fas fa-plus-circle mr-2"></i>Add Doctor
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr class="hover:bg-gray-50 transition-colors doctor-row" 
                                    data-name="<?= strtolower($doctor['fullname']) ?>" 
                                    data-email="<?= strtolower($doctor['email']) ?>"
                                    data-specialty="<?= strtolower($doctor['specialty'] ?? '') ?>"
                                    data-status="<?= $doctor['status'] ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($doctor['fullname']) ?></p>
                                                <p class="text-xs text-gray-500">ID: DOC-<?= str_pad($doctor['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?= htmlspecialchars($doctor['email']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($doctor['phone'] ?? 'No phone') ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></span>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($doctor['category_name'] ?? 'Uncategorized') ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $status_color = match($doctor['status']) {
                                            'available' => 'bg-green-100 text-green-800',
                                            'busy' => 'bg-yellow-100 text-yellow-800',
                                            'unavailable' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full <?= $status_color ?>">
                                            <?= ucfirst(htmlspecialchars($doctor['status'] ?? 'unknown')) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded">
                                                <i class="fas fa-calendar mr-1"></i><?= $doctor['total_appointments'] ?? 0 ?> appts
                                            </span>
                                            <span class="text-xs bg-green-50 text-green-600 px-2 py-1 rounded">
                                                <i class="fas fa-clock mr-1"></i><?= $doctor['available_slots'] ?? 0 ?> slots
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <a href="edit_doctor.php?id=<?= $doctor['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-800 transition-colors"
                                               title="Edit Doctor">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view_doctor.php?id=<?= $doctor['id'] ?>" 
                                               class="text-green-600 hover:text-green-800 transition-colors"
                                               title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="schedules.php?doctor_id=<?= $doctor['id'] ?>" 
                                               class="text-purple-600 hover:text-purple-800 transition-colors"
                                               title="Manage Schedule">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                            <button onclick="toggleStatus(<?= $doctor['id'] ?>)" 
                                                    class="text-yellow-600 hover:text-yellow-800 transition-colors"
                                                    title="Change Status">
                                                <i class="fas fa-toggle-on"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (visible only on mobile) -->
            <div class="md:hidden space-y-4 p-4">
                <?php if (empty($doctors)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-user-md text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No doctors found</p>
                        <a href="add_doctor.php" class="mt-4 inline-block bg-blue-600 text-white px-6 py-3 rounded-lg">
                            <i class="fas fa-plus-circle mr-2"></i>Add Doctor
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm doctor-mobile-card"
                             data-name="<?= strtolower($doctor['fullname']) ?>" 
                             data-status="<?= $doctor['status'] ?>">
                            <!-- Doctor Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($doctor['fullname']) ?></h3>
                                        <p class="text-xs text-gray-500">ID: DOC-<?= str_pad($doctor['id'], 4, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                                <?php
                                $status_color = match($doctor['status']) {
                                    'available' => 'bg-green-100 text-green-800',
                                    'busy' => 'bg-yellow-100 text-yellow-800',
                                    'unavailable' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                ?>
                                <span class="px-3 py-1 text-xs font-medium rounded-full <?= $status_color ?>">
                                    <?= ucfirst(htmlspecialchars($doctor['status'] ?? 'unknown')) ?>
                                </span>
                            </div>
                            
                            <!-- Doctor Details Grid -->
                            <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Specialty</p>
                                    <p class="font-medium"><?= htmlspecialchars($doctor['specialty'] ?? 'General') ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Category</p>
                                    <p class="font-medium"><?= htmlspecialchars($doctor['category_name'] ?? 'Uncategorized') ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Email</p>
                                    <p class="font-medium truncate"><?= htmlspecialchars($doctor['email']) ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Phone</p>
                                    <p class="font-medium"><?= htmlspecialchars($doctor['phone'] ?? 'N/A') ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Appointments</p>
                                    <p class="font-medium text-blue-600"><?= $doctor['total_appointments'] ?? 0 ?> total</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Available Slots</p>
                                    <p class="font-medium text-green-600"><?= $doctor['available_slots'] ?? 0 ?> slots</p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                                <div class="flex space-x-3">
                                    <a href="edit_doctor.php?id=<?= $doctor['id'] ?>" class="text-blue-600 hover:text-blue-800 p-2">
                                        <i class="fas fa-edit"></i>
                                        <span class="text-xs ml-1">Edit</span>
                                    </a>
                                    <a href="view_doctor.php?id=<?= $doctor['id'] ?>" class="text-green-600 hover:text-green-800 p-2">
                                        <i class="fas fa-eye"></i>
                                        <span class="text-xs ml-1">View</span>
                                    </a>
                                    <a href="schedules.php?doctor_id=<?= $doctor['id'] ?>" class="text-purple-600 hover:text-purple-800 p-2">
                                        <i class="fas fa-clock"></i>
                                        <span class="text-xs ml-1">Schedule</span>
                                    </a>
                                </div>
                                <button onclick="toggleStatus(<?= $doctor['id'] ?>)" 
                                        class="text-yellow-600 hover:text-yellow-800 p-2">
                                    <i class="fas fa-toggle-on"></i>
                                    <span class="text-xs ml-1">Status</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript for Mobile Menu and Search/Filter -->
<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }

    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        
        function filterDoctors() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            
            // Filter desktop rows
            const rows = document.querySelectorAll('.doctor-row');
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const specialty = row.dataset.specialty || '';
                const status = row.dataset.status || '';
                
                const matchesSearch = searchTerm === '' || 
                    name.includes(searchTerm) || 
                    email.includes(searchTerm) || 
                    specialty.includes(searchTerm);
                    
                const matchesStatus = statusValue === 'all' || status === statusValue;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
            
            // Filter mobile cards
            const mobileCards = document.querySelectorAll('.doctor-mobile-card');
            mobileCards.forEach(card => {
                const name = card.dataset.name || '';
                const status = card.dataset.status || '';
                
                const matchesSearch = searchTerm === '' || name.includes(searchTerm);
                const matchesStatus = statusValue === 'all' || status === statusValue;
                
                card.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        searchInput.addEventListener('input', filterDoctors);
        statusFilter.addEventListener('change', filterDoctors);
    });

    // Toggle doctor status function
    function toggleStatus(doctorId) {
        if (confirm('Change doctor status?')) {
            fetch('toggle_doctor_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'doctor_id=' + doctorId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error changing status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error changing status');
            });
        }
    }
</script>

</body>
</html>