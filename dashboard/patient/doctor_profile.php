<?php
session_start();
require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

// Get doctor ID from URL
$doctor_id = $_GET['id'] ?? 0;

if (!$doctor_id) {
    header("Location: doctors.php");
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

// Fetch doctor details with all stats
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.fullname,
        d.email,
        d.phone,
        d.specialty,
        d.status,
        d.category_id,
        dc.category_name,
        dc.description as category_description,
        (SELECT COUNT(*) FROM doctor_slots WHERE doctor_id = d.id AND is_booked = 0 AND slot_time > NOW()) as available_slots,
        (SELECT COUNT(*) FROM doctor_slots WHERE doctor_id = d.id AND slot_time > NOW()) as total_upcoming_slots,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND status = 'approved') as total_patients,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND status = 'pending') as pending_appointments,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND status = 'completed') as completed_appointments,
        COALESCE((SELECT AVG(rating) FROM doctor_ratings WHERE doctor_id = d.id), 0) as avg_rating,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id) as total_reviews,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND rating = 5) as five_star,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND rating = 4) as four_star,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND rating = 3) as three_star,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND rating = 2) as two_star,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id AND rating = 1) as one_star
    FROM doctors d
    JOIN doctor_categories dc ON d.category_id = dc.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: doctors.php");
    exit;
}

// Fetch doctor's upcoming available slots
$slot_stmt = $pdo->prepare("
    SELECT * FROM doctor_slots 
    WHERE doctor_id = ? AND is_booked = 0 AND slot_time > NOW() 
    ORDER BY slot_time ASC 
    LIMIT 5
");
$slot_stmt->execute([$doctor_id]);
$available_slots = $slot_stmt->fetchAll();

// Fetch doctor's reviews with patient details
$review_stmt = $pdo->prepare("
    SELECT 
        dr.*,
        u.fullname as patient_name,
        u.id as patient_id,
        DATE_FORMAT(dr.created_at, '%M %d, %Y') as review_date,
        DATE_FORMAT(dr.created_at, '%h:%i %p') as review_time
    FROM doctor_ratings dr
    JOIN users u ON dr.patient_id = u.id
    WHERE dr.doctor_id = ?
    ORDER BY dr.created_at DESC
");
$review_stmt->execute([$doctor_id]);
$reviews = $review_stmt->fetchAll();

// Get upcoming appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status IN ('pending', 'approved')
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_count = $stmt->fetchColumn();

// Calculate rating percentage for progress bars
$rating_percentages = [];
$total_reviews = $doctor['total_reviews'] ?: 1; // Prevent division by zero
$rating_percentages[5] = ($doctor['five_star'] / $total_reviews) * 100;
$rating_percentages[4] = ($doctor['four_star'] / $total_reviews) * 100;
$rating_percentages[3] = ($doctor['three_star'] / $total_reviews) * 100;
$rating_percentages[2] = ($doctor['two_star'] / $total_reviews) * 100;
$rating_percentages[1] = ($doctor['one_star'] / $total_reviews) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dr. <?= htmlspecialchars($doctor['fullname']) ?> | Doctor Profile</title>
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
        .rating-bar {
            transition: width 0.5s ease-in-out;
        }
        .review-card:hover {
            transform: translateX(4px);
            transition: transform 0.2s ease;
        }
        .slot-available {
            transition: all 0.2s ease;
        }
        .slot-available:hover {
            background-color: #f3f4f6;
            border-color: #3b82f6;
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
            <a href="doctors.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
        
        <!-- Breadcrumb -->
        <div class="mb-6">
            <div class="flex items-center text-sm text-gray-500">
                <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="doctors.php" class="hover:text-blue-600">Find Doctors</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-gray-700 font-medium">Dr. <?= htmlspecialchars($doctor['fullname']) ?></span>
            </div>
        </div>

        <!-- Doctor Profile Header -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
            <!-- Cover Photo -->
            <div class="h-32 bg-gradient-to-r from-blue-600 to-blue-800"></div>
            
            <!-- Profile Info -->
            <div class="px-6 pb-6">
                <div class="flex flex-col lg:flex-row lg:items-end -mt-12">
                    <!-- Doctor Avatar -->
                    <div class="flex-shrink-0">
                        <div class="w-28 h-28 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl border-4 border-white shadow-xl flex items-center justify-center text-white font-bold text-4xl">
                            <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                        </div>
                    </div>
                    
                    <!-- Doctor Basic Info -->
                    <div class="flex-1 lg:ml-6 mt-4 lg:mt-0">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                    Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                                </h1>
                                <div class="flex flex-wrap items-center gap-3 mt-2">
                                    <span class="px-4 py-1.5 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                        <i class="fas fa-stethoscope mr-1"></i>
                                        <?= htmlspecialchars($doctor['specialty']) ?>
                                    </span>
                                    <span class="px-4 py-1.5 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">
                                        <i class="fas fa-tag mr-1"></i>
                                        <?= htmlspecialchars($doctor['category_name']) ?>
                                    </span>
                                    <span class="px-4 py-1.5 <?= $doctor['status'] === 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> rounded-full text-sm font-medium">
                                        <i class="fas fa-circle mr-1 text-xs"></i>
                                        <?= $doctor['status'] === 'available' ? 'Available Now' : 'Currently Unavailable' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="mt-4 lg:mt-0 flex space-x-3">
                                <?php if ($doctor['status'] === 'available' && count($available_slots) > 0): ?>
                                    <a href="book_appointment.php?doctor_id=<?= $doctor['id'] ?>" 
                                       class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                                        <i class="fas fa-calendar-plus mr-2"></i>
                                        Book Appointment
                                    </a>
                                <?php endif; ?>
                                <a href="mailto:<?= $doctor['email'] ?>" 
                                   class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors">
                                    <i class="fas fa-envelope mr-2"></i>
                                    Contact
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mt-8">
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?= $doctor['total_patients'] ?? 0 ?></p>
                        <p class="text-xs text-gray-500">Total Patients</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?= $doctor['completed_appointments'] ?? 0 ?></p>
                        <p class="text-xs text-gray-500">Completed</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?= $doctor['available_slots'] ?? 0 ?></p>
                        <p class="text-xs text-gray-500">Available Slots</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <div class="flex items-center justify-center">
                            <span class="text-2xl font-bold text-gray-800 mr-1"><?= number_format($doctor['avg_rating'], 1) ?></span>
                            <span class="text-sm text-gray-500">/5</span>
                        </div>
                        <div class="flex items-center justify-center rating-stars mt-1">
                            <?php
                            $rating = round($doctor['avg_rating']);
                            for ($i = 1; $i <= 5; $i++):
                            ?>
                                <i class="fas fa-star <?= $i <= $rating ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-1"><?= $doctor['total_reviews'] ?> reviews</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800"><?= date('Y') - 5 ?>+</p>
                        <p class="text-xs text-gray-500">Years Experience</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column - Doctor Details & About -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- About Doctor -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-md text-blue-600 mr-3"></i>
                        About Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                    </h2>
                    <div class="space-y-4">
                        <p class="text-gray-600 leading-relaxed">
                            Dr. <?= htmlspecialchars($doctor['fullname']) ?> is a highly qualified 
                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($doctor['specialty']) ?></span> 
                            specializing in <span class="font-semibold text-gray-800"><?= htmlspecialchars($doctor['category_name']) ?></span>.
                            With extensive experience in diagnosing and treating a wide range of conditions, 
                            Dr. <?= htmlspecialchars(explode(' ', $doctor['fullname'])[1] ?? $doctor['fullname']) ?> is committed to providing 
                            compassionate, patient-centered care.
                        </p>
                        
                        <!-- Contact Information -->
                        <div class="bg-gray-50 rounded-xl p-5 mt-4">
                            <h3 class="font-semibold text-gray-800 mb-3">Contact Information</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-envelope text-blue-600 text-sm"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-gray-500">Email</p>
                                        <a href="mailto:<?= $doctor['email'] ?>" class="text-sm text-gray-800 hover:text-blue-600">
                                            <?= htmlspecialchars($doctor['email']) ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-phone-alt text-green-600 text-sm"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-xs text-gray-500">Phone</p>
                                        <a href="tel:<?= $doctor['phone'] ?>" class="text-sm text-gray-800 hover:text-blue-600">
                                            <?= htmlspecialchars($doctor['phone'] ?? 'Not available') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Qualifications (Placeholder) -->
                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="font-semibold text-gray-800 mb-3">Qualifications & Experience</h3>
                            <ul class="space-y-2">
                                <li class="flex items-start">
                                    <i class="fas fa-graduation-cap text-blue-600 mt-1 mr-3"></i>
                                    <span class="text-gray-600">MBBS, MD in <?= htmlspecialchars($doctor['specialty']) ?></span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-certificate text-blue-600 mt-1 mr-3"></i>
                                    <span class="text-gray-600">Board Certified, <?= htmlspecialchars($doctor['category_name']) ?> Association</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-trophy text-blue-600 mt-1 mr-3"></i>
                                    <span class="text-gray-600">10+ Years of Clinical Experience</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Available Slots -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-clock text-green-600 mr-3"></i>
                            Available Time Slots
                        </h2>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            <?= $doctor['available_slots'] ?? 0 ?> slots available
                        </span>
                    </div>
                    
                    <?php if (count($available_slots) > 0): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($available_slots as $slot): ?>
                                <a href="book_appointment.php?doctor_id=<?= $doctor['id'] ?>&slot_id=<?= $slot['id'] ?>" 
                                   class="slot-available flex items-center p-4 border border-gray-200 rounded-xl hover:border-blue-500 hover:shadow-md transition-all">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-calendar-check text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-800">
                                            <?= date('D, M d, Y', strtotime($slot['slot_time'])) ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?= date('h:i A', strtotime($slot['slot_time'])) ?>
                                        </p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="book_appointment.php?doctor_id=<?= $doctor['id'] ?>" 
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                                View all available slots
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-clock text-gray-400 text-xl"></i>
                            </div>
                            <p class="text-gray-600">No available slots at this time.</p>
                            <p class="text-sm text-gray-500 mt-1">Please check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column - Ratings & Reviews -->
            <div class="space-y-6">
                
                <!-- Rating Summary -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-star text-yellow-400 mr-3"></i>
                        Patient Ratings
                    </h2>
                    
                    <!-- Overall Rating -->
                    <div class="flex items-center mb-6">
                        <div class="text-5xl font-bold text-gray-800 mr-4">
                            <?= number_format($doctor['avg_rating'], 1) ?>
                        </div>
                        <div>
                            <div class="flex items-center rating-stars mb-1">
                                <?php
                                for ($i = 1; $i <= 5; $i++):
                                ?>
                                    <i class="fas fa-star <?= $i <= round($doctor['avg_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-lg"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="text-sm text-gray-500">
                                Based on <?= $doctor['total_reviews'] ?> <?= $doctor['total_reviews'] == 1 ? 'review' : 'reviews' ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Rating Distribution Bars -->
                    <div class="space-y-3">
                        <?php for ($star = 5; $star >= 1; $star--): ?>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-700 w-12"><?= $star ?> star</span>
                                <div class="flex-1 mx-3">
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-400 rating-bar" 
                                             style="width: <?= $rating_percentages[$star] ?? 0 ?>%"></div>
                                    </div>
                                </div>
                                <span class="text-sm text-gray-600 w-12">
                                    <?= $doctor["{$star}_star"] ?? 0 ?>
                                </span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Recent Reviews -->
                <div class="bg-white rounded-2xl shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-comments text-blue-600 mr-3"></i>
                            Recent Reviews
                        </h2>
                        <span class="text-sm text-gray-500">
                            <?= count($reviews) ?> total
                        </span>
                    </div>
                    
                    <?php if (count($reviews) > 0): ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto pr-1">
                            <?php foreach (array_slice($reviews, 0, 5) as $review): ?>
                                <div class="review-card p-4 bg-gray-50 rounded-xl">
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                <?= strtoupper(substr($review['patient_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-2">
                                                <p class="font-medium text-gray-800 text-sm">
                                                    <?= htmlspecialchars($review['patient_name']) ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    <?= $review['review_date'] ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-xs"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($review['review'])): ?>
                                        <p class="text-sm text-gray-600 mt-2">
                                            "<?= htmlspecialchars($review['review']) ?>"
                                        </p>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 italic mt-2">No written review</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($reviews) > 5): ?>
                            <div class="mt-4 text-center">
                                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    View all <?= count($reviews) ?> reviews
                                    <i class="fas fa-arrow-right ml-1"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-6">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-star text-gray-400 text-xl"></i>
                            </div>
                            <p class="text-gray-600">No reviews yet</p>
                            <p class="text-sm text-gray-500 mt-1">
                                Be the first to leave a review after your appointment
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Book Card -->
                <?php if ($doctor['status'] === 'available' && count($available_slots) > 0): ?>
                    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-md p-6 text-white">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-calendar-check text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold">Ready to book?</h3>
                                <p class="text-green-100 text-sm">Dr. <?= htmlspecialchars(explode(' ', $doctor['fullname'])[0]) ?> is available</p>
                            </div>
                        </div>
                        <a href="book_appointment.php?doctor_id=<?= $doctor['id'] ?>" 
                           class="block w-full py-3 bg-white hover:bg-gray-100 text-green-700 font-semibold rounded-xl text-center transition-colors">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Book Appointment Now
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Similar Doctors Section -->
        <div class="mt-8 bg-white rounded-2xl shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-user-md text-blue-600 mr-3"></i>
                Other <?= htmlspecialchars($doctor['category_name']) ?> Specialists
            </h2>
            
            <?php
            // Fetch similar doctors in same category
            $similar_stmt = $pdo->prepare("
                SELECT id, fullname, specialty, status,
                       (SELECT COUNT(*) FROM doctor_slots WHERE doctor_id = d.id AND is_booked = 0 AND slot_time > NOW()) as available_slots,
                       COALESCE((SELECT AVG(rating) FROM doctor_ratings WHERE doctor_id = d.id), 0) as avg_rating
                FROM doctors d
                WHERE category_id = ? AND id != ? AND status = 'available'
                LIMIT 4
            ");
            $similar_stmt->execute([$doctor['category_id'], $doctor['id']]);
            $similar_doctors = $similar_stmt->fetchAll();
            ?>
            
            <?php if (count($similar_doctors) > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($similar_doctors as $similar): ?>
                        <a href="doctor_profile.php?id=<?= $similar['id'] ?>" 
                           class="block p-4 border border-gray-200 rounded-xl hover:border-blue-500 hover:shadow-md transition-all">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($similar['fullname'], 0, 1)) ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-semibold text-gray-800 text-sm">Dr. <?= htmlspecialchars($similar['fullname']) ?></p>
                                    <p class="text-xs text-gray-600"><?= htmlspecialchars($similar['specialty']) ?></p>
                                    <div class="flex items-center mt-1">
                                        <div class="flex items-center rating-stars mr-2">
                                            <?php
                                            $rating = round($similar['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++):
                                            ?>
                                                <i class="fas fa-star <?= $i <= $rating ? 'text-yellow-400' : 'text-gray-300' ?> text-xxs"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-xs text-green-600">
                                            <?= $similar['available_slots'] ?> slots
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No other specialists available in this category.</p>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>