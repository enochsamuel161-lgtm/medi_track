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

// Get filter parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

// Fetch all patients with their appointment history and stats
$query = "
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.phone,
        u.date_of_birth,
        u.blood_group,
        u.allergies,
        u.address,
        u.city,
        u.state,
        u.zip_code,
        u.created_at,
        (SELECT COUNT(*) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ?) as total_visits,
        (SELECT COUNT(*) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ? AND a.status = 'completed') as completed_visits,
        (SELECT MAX(ds.slot_time) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ?) as last_visit,
        (SELECT MIN(ds.slot_time) FROM appointments a 
         JOIN doctor_slots ds ON a.slot_id = ds.id 
         WHERE a.patient_id = u.id AND a.doctor_id = ? AND ds.slot_time >= NOW() AND a.status != 'cancelled') as next_appointment,
        (SELECT COUNT(*) FROM messages m 
         WHERE m.patient_id = u.id AND m.doctor_id = ? AND m.status = 'sent') as unread_messages,
        (SELECT AVG(rating) FROM doctor_ratings dr 
         WHERE dr.patient_id = u.id AND dr.doctor_id = ?) as patient_rating
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT patient_id 
        FROM appointments 
        WHERE doctor_id = ?
        UNION
        SELECT DISTINCT patient_id 
        FROM messages 
        WHERE doctor_id = ?
    ) AND u.role = 'patient'
";

$params = [
    $doctor_id, $doctor_id, $doctor_id, $doctor_id, 
    $doctor_id, $doctor_id, $doctor_id, $doctor_id
];

// Apply search filter
if (!empty($search)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Apply sorting - FIXED for MariaDB/MySQL (NO NULLS LAST)
if ($sort === 'recent') {
    $query .= " ORDER BY last_visit DESC";
} elseif ($sort === 'name') {
    $query .= " ORDER BY u.fullname ASC";
} elseif ($sort === 'visits') {
    $query .= " ORDER BY total_visits DESC, last_visit DESC";
} elseif ($sort === 'oldest') {
    $query .= " ORDER BY last_visit ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT patient_id) as total_patients,
        COUNT(DISTINCT CASE WHEN ds.slot_time >= NOW() THEN patient_id END) as active_patients,
        COUNT(DISTINCT CASE WHEN DATE(ds.slot_time) = CURDATE() THEN patient_id END) as today_patients,
        COUNT(DISTINCT CASE WHEN ds.slot_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN patient_id END) as monthly_patients
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ? AND a.status != 'cancelled'
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch();

// Get pending appointments count for badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ? AND ds.slot_time >= NOW() AND a.status = 'pending'
");
$stmt->execute([$doctor_id]);
$pending_count = $stmt->fetchColumn();

// Get unread messages count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages 
    WHERE doctor_id = ? AND status = 'sent'
");
$stmt->execute([$doctor_id]);
$unread_messages = $stmt->fetchColumn();

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
    <title>My Patients | MediTrack</title>
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
        .patient-card:hover {
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
                    <p class="text-2xl font-bold text-white"><?= $stats['total_patients'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Total Patients</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $stats['active_patients'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Active</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $pending_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="pending_appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-hourglass-half w-5 h-5 mr-3"></i>
                <span>Pending Reviews</span>
                <?php if ($pending_count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $pending_count ?>
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
            <a href="manage_slots.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-clock w-5 h-5 mr-3"></i>
                <span>My Schedule</span>
            </a>
            <a href="patients.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>My Patients</span>
                <span class="ml-auto bg-white text-blue-800 px-2 py-0.5 rounded-full text-xs font-bold">
                    <?= $stats['total_patients'] ?? 0 ?>
                </span>
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
        
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">My Patients</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                My Patients
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Manage and view your patient roster
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Total Patients</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_patients'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Active</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['active_patients'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-heartbeat text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Today</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $stats['today_patients'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-day text-yellow-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase">This Month</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $stats['monthly_patients'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search patients by name, email, or phone..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="sm:w-48">
                    <select name="sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most Recent</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                        <option value="visits" <?= $sort === 'visits' ? 'selected' : '' ?>>Most Visits</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        Apply
                    </button>
                    <a href="patients.php" class="ml-2 px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Patients Grid -->
        <?php if (count($patients) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($patients as $patient): 
                    $age = $patient['date_of_birth'] ? (new DateTime())->diff(new DateTime($patient['date_of_birth']))->y : null;
                    $is_active = $patient['next_appointment'] ? true : false;
                    $last_visit = $patient['last_visit'] ? date('M d, Y', strtotime($patient['last_visit'])) : 'No visits yet';
                    $total_visits = $patient['total_visits'] ?? 0;
                    $unread = $patient['unread_messages'] ?? 0;
                ?>
                    <div class="patient-card bg-white rounded-2xl shadow-md hover:shadow-xl overflow-hidden border border-gray-100">
                        <div class="h-2 <?= $is_active ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-gray-400 to-gray-500' ?>"></div>
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center">
                                    <div class="relative">
                                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-2xl">
                                            <?= strtoupper(substr($patient['fullname'], 0, 1)) ?>
                                        </div>
                                        <?php if ($unread > 0): ?>
                                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center animate-pulse">
                                                <?= $unread ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($patient['fullname']) ?></h3>
                                        <p class="text-xs text-gray-500">PAT-<?= str_pad($patient['id'], 6, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                                <span class="bg-blue-100 text-blue-800 text-sm font-bold px-3 py-1 rounded-lg"><?= $total_visits ?></span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                                <div><p class="text-xs text-gray-500">Age</p><p class="font-medium"><?= $age ? $age . ' years' : '—' ?></p></div>
                                <div><p class="text-xs text-gray-500">Blood</p><p class="font-medium"><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></p></div>
                                <div class="col-span-2"><p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($patient['email']) ?></p></div>
                            </div>
                            
                            <?php if (!empty($patient['allergies'])): ?>
                                <div class="mb-4 p-2 bg-red-50 rounded-lg">
                                    <p class="text-xs font-medium text-red-700"><i class="fas fa-exclamation-triangle mr-1"></i>Allergies: <?= htmlspecialchars($patient['allergies']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="bg-gray-50 rounded-lg p-3 mb-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-xs text-gray-500"><i class="fas fa-calendar-alt mr-1"></i>Last visit</span>
                                    <span class="text-xs font-medium"><?= $last_visit ?></span>
                                </div>
                                <?php if ($patient['next_appointment']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-xs text-gray-500"><i class="fas fa-clock mr-1"></i>Next</span>
                                        <span class="text-xs font-medium text-green-600"><?= date('M d, Y', strtotime($patient['next_appointment'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-2">
                                <a href="patient_details.php?id=<?= $patient['id'] ?>" class="flex flex-col items-center p-2 bg-blue-50 hover:bg-blue-100 rounded-lg">
                                    <i class="fas fa-user-circle text-blue-600"></i>
                                    <span class="text-xs text-gray-700 mt-1">Profile</span>
                                </a>
                                <a href="appointments.php?patient=<?= $patient['id'] ?>" class="flex flex-col items-center p-2 bg-green-50 hover:bg-green-100 rounded-lg">
                                    <i class="fas fa-calendar-check text-green-600"></i>
                                    <span class="text-xs text-gray-700 mt-1">History</span>
                                </a>
                                <a href="messages.php?patient=<?= $patient['id'] ?>" class="flex flex-col items-center p-2 bg-purple-50 hover:bg-purple-100 rounded-lg relative">
                                    <i class="fas fa-comment text-purple-600"></i>
                                    <span class="text-xs text-gray-700 mt-1">Message</span>
                                    <?php if ($unread > 0): ?>
                                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center"><?= $unread ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-users text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Patients Found</h3>
                <p class="text-gray-600 mb-6">
                    <?= !empty($search) ? 'No patients match your search criteria.' : 'You don\'t have any patients yet.' ?>
                </p>
                <a href="manage_slots.php" class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg">
                    <i class="fas fa-clock mr-2"></i>Add Availability
                </a>
            </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>