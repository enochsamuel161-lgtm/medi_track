<?php
session_start();
require "../../config/database.php";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | MediTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same as patient dashboard -->
    <aside class="lg:w-80 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
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
            <a href="medical_records.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-notes-medical w-5 h-5 mr-3"></i>
                <span>Medical Records</span>
            </a>
            <a href="messages.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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

    <!-- MAIN CONTENT - COMING SOON MESSAGE -->
    <main class="flex-1 bg-gray-50 p-4 lg:p-8 flex items-center justify-center">
        <div class="max-w-2xl w-full text-center">
            <!-- Construction Illustration -->
            <div class="mb-8 relative">
                <div class="w-40 h-40 bg-blue-100 rounded-full mx-auto flex items-center justify-center">
                    <i class="fas fa-comments text-blue-600 text-7xl"></i>
                </div>
                <div class="absolute -top-3 -right-3 md:right-32 lg:right-48 animate-bounce bg-yellow-400 text-white w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold">
                    <span>SOON</span>
                </div>
            </div>
            
            <!-- Coming Soon Text -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
                Messages Coming Soon!
            </h1>
            
            <p class="text-xl text-gray-600 mb-8">
                We're working hard to bring you a secure messaging system to communicate with your doctors. 
                Stay tuned for updates!
            </p>
            
            <!-- Features Preview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-lock text-green-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Secure</h3>
                    <p class="text-sm text-gray-500">End-to-end encrypted</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-bolt text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Real-time</h3>
                    <p class="text-sm text-gray-500">Instant delivery</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-image text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Media Support</h3>
                    <p class="text-sm text-gray-500">Share images & files</p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="index.php" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors shadow-md">
                    <i class="fas fa-home mr-2"></i>
                    Back to Dashboard
                </a>
                <a href="categories.php" class="px-8 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors shadow-md">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Book Appointment
                </a>
            </div>
            
            <!-- Notification Signup -->
            <div class="mt-10 p-6 bg-gray-100 rounded-xl">
                <p class="text-gray-600 mb-3">
                    <i class="fas fa-bell mr-2 text-blue-600"></i>
                    Want to be notified when messages launch?
                </p>
                <div class="flex max-w-md mx-auto">
                    <input type="email" placeholder="Enter your email" class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button class="px-6 py-2 bg-blue-600 text-white rounded-r-lg hover:bg-blue-700">
                        Notify Me
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>