<?php
session_start();
require "../../config/database.php";

// Check if doctor is logged in
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

// Handle AJAX appointment update
if (isset($_POST['ajax_update'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ?, notes = ? WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$status, $notes, $appointment_id, $doctor_id]);
        
        // If status is accepted, also mark slot as booked
        if ($status === 'accepted' || $status === 'approved') {
            $stmt = $pdo->prepare("
                UPDATE doctor_slots ds 
                JOIN appointments a ON ds.id = a.slot_id 
                SET ds.is_booked = 1 
                WHERE a.id = ?
            ");
            $stmt->execute([$appointment_id]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle bulk status update
if (isset($_POST['bulk_update'])) {
    $appointment_ids = $_POST['appointment_ids'] ?? [];
    $status = $_POST['bulk_status'];
    
    if (count($appointment_ids) > 0) {
        try {
            $placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));
            $params = array_merge($appointment_ids, [$doctor_id]);
            
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ? 
                WHERE id IN ($placeholders) AND doctor_id = ?
            ");
            array_unshift($params, $status);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'count' => count($appointment_ids)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Get filter parameters
$filter_date = $_GET['date'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$filter_patient = $_GET['patient'] ?? '';

// Fetch appointments for this doctor with filters
$query = "
    SELECT 
        a.*, 
        u.fullname AS patient_name,
        u.email AS patient_email,
        u.phone AS patient_phone,
        u.date_of_birth,
        u.blood_group,
        s.slot_time,
        s.notes AS slot_notes,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = a.patient_id AND doctor_id = a.doctor_id AND status = 'completed') as visit_count
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    JOIN doctor_slots s ON s.id = a.slot_id
    WHERE a.doctor_id = ?
";

$params = [$doctor_id];

if (!empty($filter_date)) {
    $query .= " AND DATE(s.slot_time) = ?";
    $params[] = $filter_date;
}

if ($filter_status !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_patient)) {
    $query .= " AND u.fullname LIKE ?";
    $params[] = "%$filter_patient%";
}

$query .= " ORDER BY s.slot_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'accepted' OR status = 'approved' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' OR status = 'cancelled' THEN 1 ELSE 0 END) as rejected,
        COUNT(DISTINCT patient_id) as unique_patients
    FROM appointments a
    JOIN doctor_slots s ON s.id = a.slot_id
    WHERE a.doctor_id = ?
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch();

// Get today's appointments count
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN doctor_slots s ON s.id = a.slot_id
    WHERE a.doctor_id = ? AND DATE(s.slot_time) = CURDATE()
");
$stmt->execute([$doctor_id]);
$today_count = $stmt->fetchColumn();

// Get upcoming appointments count for badge
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
    <title>Doctor Appointments | MediTrack</title>
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
        .appointment-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .filter-active {
            background-color: #ebf8ff;
            border-color: #4299e1;
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
                    <p class="text-2xl font-bold text-white"><?= $stats['unique_patients'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Total Patients</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $today_count ?></p>
                    <p class="text-xs text-blue-200">Today</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-4 space-y-2">
            <a href="index.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-dashboard w-5 h-5 mr-3"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
        
        <!-- Header with Breadcrumb -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Appointments</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Appointments
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Manage and update your patient appointments
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <a href="manage_slots.php" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-clock mr-2"></i>
                        Manage Schedule
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-blue-500">
                <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Total</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-yellow-500">
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Pending</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-green-500">
                <p class="text-2xl font-bold text-green-600"><?= $stats['accepted'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Accepted</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-blue-500">
                <p class="text-2xl font-bold text-blue-600"><?= $stats['completed'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Completed</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-red-500">
                <p class="text-2xl font-bold text-red-600"><?= $stats['rejected'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Rejected</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4 text-center border-l-4 border-purple-500">
                <p class="text-2xl font-bold text-purple-600"><?= $stats['unique_patients'] ?? 0 ?></p>
                <p class="text-xs text-gray-500">Patients</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="" class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="accepted" <?= $filter_status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Patient</label>
                    <input type="text" name="patient" value="<?= htmlspecialchars($filter_patient) ?>" 
                           placeholder="Patient name..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Apply
                    </button>
                    <a href="appointments.php" class="ml-2 px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <?php if (count($appointments) > 0): ?>
        <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex flex-col sm:flex-row items-center justify-between">
            <div class="flex items-center mb-3 sm:mb-0">
                <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="selectAll" class="ml-2 text-sm text-gray-700">Select All</label>
            </div>
            
            <div class="flex items-center space-x-3">
                <select id="bulkStatusSelect" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Bulk Action</option>
                    <option value="accepted">Mark as Accepted</option>
                    <option value="completed">Mark as Completed</option>
                    <option value="rejected">Mark as Rejected</option>
                </select>
                <button id="bulkUpdateBtn" 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    Apply to Selected
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Appointments List -->
        <?php if (count($appointments) > 0): ?>
            <!-- Desktop Table View (hidden on mobile) -->
            <div class="hidden md:block bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider w-8">
                                    <input type="checkbox" id="selectAllDesktop" class="w-4 h-4 rounded border-white bg-white/20 checked:bg-blue-500">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Notes</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php foreach ($appointments as $a): ?>
                                <tr class="hover:bg-gray-50 transition-colors appointment-row" data-id="<?= $a['id']; ?>">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" class="appointment-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                <?= strtoupper(substr($a['patient_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($a['patient_name']) ?></p>
                                                <p class="text-xs text-gray-500">
                                                    <?= $a['visit_count'] > 0 ? $a['visit_count'] . ' visits' : 'New patient' ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?= date("M d, Y", strtotime($a['slot_time'])); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?= date("h:i A", strtotime($a['slot_time'])); ?>
                                        </p>
                                        <?php if (date('Y-m-d', strtotime($a['slot_time'])) == date('Y-m-d')): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mt-1">
                                                Today
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select class="status text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-32">
                                            <option value="pending" <?= $a['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                            <option value="accepted" <?= $a['status'] === 'accepted' ? 'selected' : '' ?>>✅ Accepted</option>
                                            <option value="completed" <?= $a['status'] === 'completed' ? 'selected' : '' ?>>✓ Completed</option>
                                            <option value="rejected" <?= $a['status'] === 'rejected' ? 'selected' : '' ?>>❌ Rejected</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" class="notes text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full" 
                                               value="<?= htmlspecialchars($a['notes'] ?? ''); ?>" 
                                               placeholder="Add notes...">
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center space-x-2">
                                            <button class="update-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors">
                                                Update
                                            </button>
                                            <a href="patient_details.php?id=<?= $a['patient_id'] ?>" 
                                               class="text-gray-600 hover:text-blue-600" title="View Patient">
                                                <i class="fas fa-user-circle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden space-y-4">
                <?php foreach ($appointments as $a): ?>
                    <div class="appointment-card bg-white rounded-xl shadow-md p-5 border border-gray-100" data-id="<?= $a['id']; ?>">
                        <!-- Patient Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" class="appointment-checkbox-mobile w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 mr-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                    <?= strtoupper(substr($a['patient_name'], 0, 1)) ?>
                                </div>
                                <div class="ml-3">
                                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($a['patient_name']) ?></h3>
                                    <p class="text-xs text-gray-500">
                                        <?= $a['visit_count'] > 0 ? $a['visit_count'] . ' previous visits' : 'New patient' ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (date('Y-m-d', strtotime($a['slot_time'])) == date('Y-m-d')): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                    Today
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Appointment Details -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="text-sm font-medium"><?= date("M d, Y", strtotime($a['slot_time'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Time</p>
                                    <p class="text-sm font-medium"><?= date("h:i A", strtotime($a['slot_time'])); ?></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-500">Status</p>
                                    <select class="status text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 w-full mt-1">
                                        <option value="pending" <?= $a['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                        <option value="accepted" <?= $a['status'] === 'accepted' ? 'selected' : '' ?>>✅ Accepted</option>
                                        <option value="completed" <?= $a['status'] === 'completed' ? 'selected' : '' ?>>✓ Completed</option>
                                        <option value="rejected" <?= $a['status'] === 'rejected' ? 'selected' : '' ?>>❌ Rejected</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-500">Notes</p>
                                    <input type="text" class="notes text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 w-full mt-1" 
                                           value="<?= htmlspecialchars($a['notes'] ?? ''); ?>" 
                                           placeholder="Add appointment notes...">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-center space-x-3">
                            <button class="update-btn flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Update
                            </button>
                            <a href="patient_details.php?id=<?= $a['patient_id'] ?>" 
                               class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                                <i class="fas fa-user-circle mr-2"></i>
                                Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-calendar-times text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Appointments Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    <?php if (!empty($filter_date) || !empty($filter_status) || !empty($filter_patient)): ?>
                        No appointments match your filters. Try adjusting your search criteria.
                    <?php else: ?>
                        You don't have any appointments scheduled yet. Appointments will appear here when patients book.
                    <?php endif; ?>
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <?php if (!empty($filter_date) || !empty($filter_status) || !empty($filter_patient)): ?>
                        <a href="appointments.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md">
                            <i class="fas fa-times mr-2"></i>
                            Clear Filters
                        </a>
                    <?php endif; ?>
                    <a href="manage_slots.php" class="inline-flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors shadow-md">
                        <i class="fas fa-clock mr-2"></i>
                        Manage Schedule
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Appointment Tips -->
        <?php if (count($appointments) > 0): ?>
        <div class="mt-8 bg-blue-50 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-lightbulb text-white text-xl"></i>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-4">
                    <h4 class="text-lg font-semibold text-gray-800">Appointment Management Tips</h4>
                    <ul class="mt-2 text-sm text-gray-600 space-y-1">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Update appointment status promptly to keep patients informed
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Add notes about diagnosis, prescriptions, or follow-up requirements
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Use the patient profile link to view medical history before appointments
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Individual update buttons
    document.querySelectorAll('.update-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr') || this.closest('.appointment-card');
            const appointment_id = row.dataset.id;
            const status = row.querySelector('.status').value;
            const notes = row.querySelector('.notes').value;

            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            this.disabled = true;

            fetch('appointments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_update=1&appointment_id=${appointment_id}&status=${encodeURIComponent(status)}&notes=${encodeURIComponent(notes)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.innerHTML = '<i class="fas fa-check mr-2"></i>Updated!';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                } else {
                    this.innerHTML = originalText;
                    this.disabled = false;
                    alert('Error updating appointment');
                }
            })
            .catch(error => {
                this.innerHTML = originalText;
                this.disabled = false;
                alert('Error: ' + error);
            });
        });
    });

    // Select All functionality (Desktop)
    const selectAllDesktop = document.getElementById('selectAllDesktop');
    const selectAllMobile = document.getElementById('selectAll');
    const desktopCheckboxes = document.querySelectorAll('.appointment-row .appointment-checkbox');
    const mobileCheckboxes = document.querySelectorAll('.appointment-checkbox-mobile');

    if (selectAllDesktop) {
        selectAllDesktop.addEventListener('change', function() {
            desktopCheckboxes.forEach(cb => cb.checked = this.checked);
        });
    }

    if (selectAllMobile) {
        selectAllMobile.addEventListener('change', function() {
            mobileCheckboxes.forEach(cb => cb.checked = this.checked);
        });
    }

    // Bulk Update
    const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
    if (bulkUpdateBtn) {
        bulkUpdateBtn.addEventListener('click', function() {
            const status = document.getElementById('bulkStatusSelect').value;
            if (!status) {
                alert('Please select a status to apply');
                return;
            }

            // Get selected appointment IDs
            const selectedIds = [];
            desktopCheckboxes.forEach(cb => {
                if (cb.checked) {
                    const row = cb.closest('tr');
                    if (row) selectedIds.push(row.dataset.id);
                }
            });
            mobileCheckboxes.forEach(cb => {
                if (cb.checked) {
                    const card = cb.closest('.appointment-card');
                    if (card) selectedIds.push(card.dataset.id);
                }
            });

            if (selectedIds.length === 0) {
                alert('Please select at least one appointment');
                return;
            }

            if (confirm(`Update ${selectedIds.length} appointment(s) to ${status}?`)) {
                fetch('appointments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `bulk_update=1&bulk_status=${status}&appointment_ids=${selectedIds.join(',')}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating appointments');
                    }
                });
            }
        });
    }
});
</script>

</body>
</html>