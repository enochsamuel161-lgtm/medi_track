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

// Get category_id from URL
$category_id = $_GET['category_id'] ?? 0;

// Redirect to categories page if no category selected
if (!$category_id) {
    header("Location: categories.php");
    exit;
}

// Get category name for display
$cat_stmt = $pdo->prepare("SELECT category_name, description FROM doctor_categories WHERE id = ?");
$cat_stmt->execute([$category_id]);
$category = $cat_stmt->fetch();

if (!$category) {
    header("Location: categories.php");
    exit;
}

// Fetch doctors from this specific category with additional info
$stmt = $pdo->prepare("
    SELECT id, fullname, specialty, status, email, phone 
    FROM doctors 
    WHERE category_id = ? 
    ORDER BY 
        CASE WHEN status = 'available' THEN 1 ELSE 2 END,
        fullname ASC
");
$stmt->execute([$category_id]);
$doctors = $stmt->fetchAll();

// Handle booking
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $slot_id = $_POST['slot_id'] ?? null;
    $doctor_id = $_POST['doctor_id'] ?? null;
    $patient_id = $_SESSION['user_id'];

    if (!$slot_id || !$doctor_id) {
        $error = "Please select a doctor and a time slot.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Lock the slot to avoid double booking
            $stmt = $pdo->prepare("SELECT is_booked FROM doctor_slots WHERE id = ? FOR UPDATE");
            $stmt->execute([$slot_id]);
            $slot = $stmt->fetch();

            if (!$slot) {
                $pdo->rollBack();
                $error = "Selected slot does not exist.";
            } elseif ($slot['is_booked']) {
                $pdo->rollBack();
                $error = "This slot has already been booked.";
            } else {
                // Insert appointment
                $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, slot_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                $stmt->execute([$patient_id, $doctor_id, $slot_id]);

                // Mark slot as booked
                $stmt = $pdo->prepare("UPDATE doctor_slots SET is_booked = 1 WHERE id = ?");
                $stmt->execute([$slot_id]);

                $pdo->commit();
                $success = "Appointment booked successfully! You can view it in My Appointments.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error booking appointment: " . $e->getMessage();
        }
    }
}

// Get upcoming appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.patient_id = ? AND ds.slot_time >= NOW() AND a.status IN ('pending', 'approved')
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Book Appointment - <?= htmlspecialchars($category['category_name']) ?> | MediTrack</title>
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
        .doctor-card:hover .doctor-avatar {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }
        .slot-option:hover {
            background-color: #f3f4f6;
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
            <div class="mt-6 grid grid-cols-2 gap-3 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= count($doctors) ?></p>
                    <p class="text-xs text-blue-200">Available Doctors</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $upcoming_count ?></p>
                    <p class="text-xs text-blue-200">Upcoming</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="categories.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
                        <a href="categories.php" class="hover:text-blue-600">Categories</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium"><?= htmlspecialchars($category['category_name']) ?></span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <?php
                            $icon = 'fa-stethoscope';
                            $cat_name = strtolower($category['category_name']);
                            if (strpos($cat_name, 'cardio') !== false) $icon = 'fa-heart';
                            else if (strpos($cat_name, 'neuro') !== false) $icon = 'fa-brain';
                            else if (strpos($cat_name, 'derma') !== false) $icon = 'fa-allergies';
                            else if (strpos($cat_name, 'pedia') !== false) $icon = 'fa-child';
                            else if (strpos($cat_name, 'ortho') !== false) $icon = 'fa-bone';
                            else if (strpos($cat_name, 'optha') !== false) $icon = 'fa-eye';
                            else if (strpos($cat_name, 'dental') !== false) $icon = 'fa-tooth';
                            else if (strpos($cat_name, 'gyne') !== false) $icon = 'fa-female';
                            else if (strpos($cat_name, 'psych') !== false) $icon = 'fa-smile';
                            ?>
                            <i class="fas <?= $icon ?> text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                <?= htmlspecialchars($category['category_name']) ?> Specialists
                            </h1>
                            <?php if (!empty($category['description'])): ?>
                                <p class="text-gray-600 mt-1">
                                    <?= htmlspecialchars($category['description']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0">
                    <a href="categories.php" 
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg transition-colors shadow-sm">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Categories
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

        <!-- Doctors List -->
        <?php if (count($doctors) > 0): ?>
            <div class="space-y-6">
                <?php 
                $has_available = false;
                foreach($doctors as $doctor): 
                    if ($doctor['status'] === 'available') $has_available = true;
                ?>
                    <div class="doctor-card bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
                        <!-- Status Bar -->
                        <div class="h-2 <?= $doctor['status'] === 'available' ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-gray-400 to-gray-500' ?>"></div>
                        
                        <div class="p-6">
                            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                                <!-- Doctor Info -->
                                <div class="flex-1">
                                    <div class="flex items-start">
                                        <!-- Doctor Avatar -->
                                        <div class="doctor-avatar w-16 h-16 <?= $doctor['status'] === 'available' ? 'bg-gradient-to-br from-blue-500 to-blue-600' : 'bg-gradient-to-br from-gray-500 to-gray-600' ?> rounded-xl flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                                            <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                                        </div>
                                        
                                        <div class="ml-4 flex-1">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <h2 class="text-2xl font-bold text-gray-800">
                                                        Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                                                    </h2>
                                                    <p class="text-gray-600 mt-1 flex items-center">
                                                        <i class="fas fa-stethoscope text-blue-500 mr-2"></i>
                                                        <?= htmlspecialchars($doctor['specialty']) ?>
                                                    </p>
                                                </div>
                                                
                                                <!-- Status Badge -->
                                                <div class="mt-2 sm:mt-0">
                                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold <?= $doctor['status'] === 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                        <span class="w-2 h-2 <?= $doctor['status'] === 'available' ? 'bg-green-500' : 'bg-red-500' ?> rounded-full mr-2"></span>
                                                        <?= $doctor['status'] === 'available' ? 'Available Today' : 'Currently Unavailable' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Doctor Contact (Optional) -->
                                            <?php if (!empty($doctor['phone'])): ?>
                                                <p class="text-sm text-gray-500 mt-2 flex items-center">
                                                    <i class="fas fa-phone-alt text-gray-400 mr-2"></i>
                                                    <?= htmlspecialchars($doctor['phone']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Appointment Section -->
                                    <?php if($doctor['status'] === 'available'): ?>
                                        <div class="mt-6 pt-4 border-t border-gray-100">
                                            <?php
                                            // Fetch available slots for this doctor
                                            $slot_stmt = $pdo->prepare("
                                                SELECT * FROM doctor_slots 
                                                WHERE doctor_id = ? AND is_booked = 0 AND slot_time > NOW() 
                                                ORDER BY slot_time ASC
                                            ");
                                            $slot_stmt->execute([$doctor['id']]);
                                            $slots = $slot_stmt->fetchAll();
                                            ?>
                                            
                                            <?php if($slots): ?>
                                                <div class="flex items-center mb-3">
                                                    <i class="fas fa-clock text-green-500 mr-2"></i>
                                                    <h3 class="font-semibold text-gray-700">Available Time Slots</h3>
                                                    <span class="ml-2 bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded-full">
                                                        <?= count($slots) ?> slots
                                                    </span>
                                                </div>
                                                
                                                <form method="POST" class="space-y-4">
                                                    <input type="hidden" name="doctor_id" value="<?= $doctor['id']; ?>">
                                                    
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        <?php foreach($slots as $slot): ?>
                                                            <label class="slot-option relative block border rounded-lg p-4 cursor-pointer transition-all hover:border-blue-400 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                                                                <input type="radio" name="slot_id" value="<?= $slot['id']; ?>" class="sr-only" required>
                                                                <div class="flex items-center">
                                                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                                        <i class="fas fa-calendar-check text-blue-600 text-sm"></i>
                                                                    </div>
                                                                    <div class="ml-3">
                                                                        <p class="text-sm font-medium text-gray-900">
                                                                            <?= date("D, M d", strtotime($slot['slot_time'])); ?>
                                                                        </p>
                                                                        <p class="text-xs text-gray-500">
                                                                            <?= date("h:i A", strtotime($slot['slot_time'])); ?>
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    
                                                    <div class="flex justify-end">
                                                        <button type="submit" name="book_appointment" 
                                                                class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-8 rounded-lg transition-all transform hover:scale-105 shadow-md hover:shadow-lg flex items-center">
                                                            <i class="fas fa-calendar-check mr-2"></i>
                                                            Book Appointment
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <div class="bg-yellow-50 rounded-lg p-4">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <p class="text-sm text-yellow-700">
                                                                No available slots at this time. Please check back later.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (!$has_available): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-yellow-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-yellow-800">All Doctors Currently Unavailable</h3>
                                <p class="text-yellow-700 mt-1">
                                    There are no available doctors in this specialty at the moment. 
                                    Please check back later or browse other categories.
                                </p>
                                <a href="categories.php" class="inline-flex items-center mt-4 text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Browse Other Categories
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- No Doctors Found -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user-md text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Doctors Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    There are currently no doctors available in the 
                    <span class="font-semibold"><?= htmlspecialchars($category['category_name']) ?></span> category.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="categories.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Browse Other Categories
                    </a>
                    <a href="index.php" class="inline-flex items-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        <i class="fas fa-home mr-2"></i>
                        Go to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Appointment Tips -->
        <div class="mt-8 bg-blue-50 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-lightbulb text-white text-xl"></i>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-4">
                    <h4 class="text-lg font-semibold text-gray-800">Tips for Booking Appointments</h4>
                    <ul class="mt-2 text-sm text-gray-600 space-y-1">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Select a time slot that works best for you
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Arrive 15 minutes before your scheduled time
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Bring your ID and insurance card to the appointment
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript for enhanced interactions -->
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

    // Highlight selected slot
    document.querySelectorAll('input[name="slot_id"]').forEach(radio => {
        radio.addEventListener('change', function() {
            // Remove highlight from all labels
            document.querySelectorAll('.slot-option').forEach(label => {
                label.classList.remove('border-blue-600', 'bg-blue-50');
                label.classList.add('border-gray-200');
            });
            // Add highlight to selected label
            this.closest('label').classList.remove('border-gray-200');
            this.closest('label').classList.add('border-blue-600', 'bg-blue-50');
        });
    });
</script>

</body>
</html>