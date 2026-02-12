<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM doctor_categories ORDER BY category_name")->fetchAll();

// Get admin info for sidebar
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

$stmt = $pdo->query("SELECT COUNT(*) FROM doctor_categories");
$total_categories_sidebar = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin | Add Doctor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same style as other admin pages -->
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
            <a href="doctors.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
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
                <span class="ml-auto bg-blue-600 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_categories_sidebar ?></span>
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
        
        <!-- Header with Breadcrumb -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <a href="doctors.php" class="hover:text-blue-600">Doctors</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Add Doctor</span>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Add New Doctor</h1>
                    <p class="text-sm text-gray-600 mt-1">Register a new doctor to the hospital system</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <a href="doctors.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Doctors
                    </a>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Form Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold text-white">Doctor Information</h2>
                            <p class="text-blue-100 text-sm">Fill in the details to add a new doctor</p>
                        </div>
                    </div>
                </div>

                <!-- Form Body -->
                <form action="save_doctor.php" method="POST" class="p-6">
                    <div class="space-y-6">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user text-blue-500 mr-2"></i>
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="fullname"
                                placeholder="e.g., Dr. John Smith"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white"
                            >
                            <p class="text-xs text-gray-500 mt-1">Enter the doctor's full name as per medical license</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-blue-500 mr-2"></i>
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                name="email"
                                placeholder="doctor@hospital.com"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white"
                            >
                            <p class="text-xs text-gray-500 mt-1">This will be used as login email</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-phone text-blue-500 mr-2"></i>
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="tel"
                                name="phone"
                                placeholder="e.g., (555) 123-4567"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white"
                            >
                        </div>

                        <!-- Specialty -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-stethoscope text-blue-500 mr-2"></i>
                                Specialty <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="specialty"
                                placeholder="e.g., Cardiologist, Dermatologist"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white"
                            >
                        </div>

                        <!-- Category Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-tags text-blue-500 mr-2"></i>
                                Doctor Category <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    name="category_id"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white appearance-none"
                                >
                                    <option value="" disabled selected>-- Select a Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" class="py-2">
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                            <?php if (!empty($cat['description'])): ?>
                                                - <?= htmlspecialchars(substr($cat['description'], 0, 30)) ?>...
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-500"></i>
                                </div>
                            </div>
                            <?php if (empty($categories)): ?>
                                <p class="text-xs text-red-500 mt-2">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    No categories found. 
                                    <a href="categories.php" class="text-blue-600 hover:underline">Create a category first</a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-lock text-blue-500 mr-2"></i>
                                Login Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    placeholder="Enter secure password"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white pr-12"
                                >
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-blue-600">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="flex items-center mt-2 text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                <span>Password must be at least 8 characters</span>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="submit"
                                class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold px-6 py-3 rounded-lg transition-all transform hover:scale-105 flex items-center justify-center"
                            >
                                <i class="fas fa-plus-circle mr-2"></i>
                                Add Doctor
                            </button>
                            <a
                                href="doctors.php"
                                class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-6 py-3 rounded-lg transition-all flex items-center justify-center"
                            >
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Quick Tips Card -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-lightbulb text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Quick Tips</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Use the doctor's full professional name</li>
                                <li>Ensure email is unique and valid</li>
                                <li>Assign the correct specialization category</li>
                                <li>Share login credentials securely with the doctor</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript for Mobile Menu and Password Toggle -->
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

    // Password visibility toggle
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long');
        }
    });
</script>

</body>
</html>