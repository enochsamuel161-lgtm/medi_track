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
}

// Handle add slot
if (isset($_POST['add_slot'])) {
    $slot_time = $_POST['slot_time'];
    $notes = $_POST['notes'] ?? '';
    
    if (!empty($slot_time)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_slots (doctor_id, slot_time, is_booked, notes, created_at)
                VALUES (?, ?, 0, ?, NOW())
            ");
            $stmt->execute([$doctor_id, $slot_time, $notes]);
            $success = "Availability slot added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding slot: " . $e->getMessage();
        }
    }
}

// Handle delete slot
if (isset($_GET['delete'])) {
    $slot_id = (int) $_GET['delete'];
    
    try {
        // Check if slot is booked
        $stmt = $pdo->prepare("SELECT is_booked FROM doctor_slots WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$slot_id, $doctor_id]);
        $slot = $stmt->fetch();
        
        if ($slot && !$slot['is_booked']) {
            $stmt = $pdo->prepare("DELETE FROM doctor_slots WHERE id = ? AND doctor_id = ?");
            $stmt->execute([$slot_id, $doctor_id]);
            $success = "Slot deleted successfully!";
        } else {
            $error = "Cannot delete booked slot!";
        }
    } catch (PDOException $e) {
        $error = "Error deleting slot: " . $e->getMessage();
    }
}

// Handle bulk add slots
if (isset($_POST['bulk_add_slots'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $slot_duration = (int) ($_POST['slot_duration'] ?? 30);
    $break_duration = (int) ($_POST['break_duration'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if (!empty($start_date) && !empty($end_date) && !empty($start_time) && !empty($end_time)) {
        try {
            $pdo->beginTransaction();
            
            $start = new DateTime($start_date . ' ' . $start_time);
            $end = new DateTime($end_date . ' ' . $end_time);
            $interval = new DateInterval('PT' . $slot_duration . 'M');
            $break_interval = $break_duration > 0 ? new DateInterval('PT' . $break_duration . 'M') : null;
            
            $current = clone $start;
            $added_count = 0;
            
            while ($current <= $end) {
                // Check if slot already exists
                $check = $pdo->prepare("
                    SELECT id FROM doctor_slots 
                    WHERE doctor_id = ? AND slot_time = ?
                ");
                $check->execute([$doctor_id, $current->format('Y-m-d H:i:s')]);
                
                if ($check->rowCount() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_slots (doctor_id, slot_time, is_booked, notes, created_at)
                        VALUES (?, ?, 0, ?, NOW())
                    ");
                    $stmt->execute([$doctor_id, $current->format('Y-m-d H:i:s'), $notes]);
                    $added_count++;
                }
                
                $current->add($interval);
                if ($break_interval) {
                    $current->add($break_interval);
                }
            }
            
            $pdo->commit();
            $success = "Successfully added $added_count availability slots!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error adding bulk slots: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? 'all';

// Fetch slots with filters
$query = "
    SELECT * FROM doctor_slots 
    WHERE doctor_id = ?
";
$params = [$doctor_id];

if ($filter_date !== 'all') {
    $query .= " AND DATE(slot_time) = ?";
    $params[] = $filter_date;
}

if ($filter_status === 'available') {
    $query .= " AND is_booked = 0";
} elseif ($filter_status === 'booked') {
    $query .= " AND is_booked = 1";
}

$query .= " ORDER BY slot_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$slots = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_booked = 0 AND slot_time > NOW() THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN is_booked = 1 THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN is_booked = 0 AND slot_time < NOW() THEN 1 ELSE 0 END) as expired
    FROM doctor_slots 
    WHERE doctor_id = ?
");
$stmt->execute([$doctor_id]);
$stats = $stmt->fetch();

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
    <title>Manage Schedule | MediTrack</title>
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
        .slot-card:hover {
            transform: translateY(-2px);
            transition: transform 0.3s ease;
        }
        .datepicker {
            position: relative;
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
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-2 gap-3 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $stats['available'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Available</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $stats['booked'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Booked</p>
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
            <a href="messages.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-xl transition-all">
                <i class="fas fa-comments w-5 h-5 mr-3"></i>
                <span>Patient Messages</span>
                <?php if ($unread_messages > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse">
                        <?= $unread_messages ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="manage_slots.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
                        <span class="text-gray-700 font-medium">Manage Schedule</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Manage Schedule
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Set your availability and manage time slots
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <button onclick="openAddModal()" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-plus-circle mr-2"></i>
                        Add Slot
                    </button>
                    <button onclick="openBulkModal()" 
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors shadow-md">
                        <i class="fas fa-calendar-plus mr-2"></i>
                        Bulk Add
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Total Slots</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Available</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['available'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Booked</p>
                        <p class="text-2xl font-bold text-purple-600"><?= $stats['booked'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-bookmark text-purple-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-md p-5 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Expired</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $stats['expired'] ?? 0 ?></p>
                    </div>
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Slots</option>
                        <option value="available" <?= $filter_status === 'available' ? 'selected' : '' ?>>Available Only</option>
                        <option value="booked" <?= $filter_status === 'booked' ? 'selected' : '' ?>>Booked Only</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Apply Filters
                    </button>
                    <a href="manage_slots.php" class="ml-2 px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Slots Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php if (count($slots) > 0): ?>
                <?php foreach ($slots as $slot): 
                    $is_past = strtotime($slot['slot_time']) < time();
                    $is_booked = $slot['is_booked'] == 1;
                ?>
                    <div class="slot-card bg-white rounded-xl shadow-md overflow-hidden border <?= $is_booked ? 'border-purple-200' : ($is_past ? 'border-gray-200' : 'border-green-200') ?>">
                        <div class="p-5">
                            <!-- Date Header -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 <?= $is_booked ? 'bg-purple-100' : ($is_past ? 'bg-gray-100' : 'bg-green-100') ?> rounded-full flex items-center justify-center">
                                        <i class="fas <?= $is_booked ? 'fa-bookmark text-purple-600' : ($is_past ? 'fa-clock text-gray-600' : 'fa-check-circle text-green-600') ?>"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-800">
                                            <?= date('D, M d', strtotime($slot['slot_time'])) ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?= date('h:i A', strtotime($slot['slot_time'])) ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <?php if ($is_booked): ?>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-medium">
                                        Booked
                                    </span>
                                <?php elseif ($is_past): ?>
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                                        Expired
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                        Available
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Notes -->
                            <?php if (!empty($slot['notes'])): ?>
                                <p class="text-xs text-gray-500 mt-2 bg-gray-50 p-2 rounded">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <?= htmlspecialchars($slot['notes']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Actions -->
                            <div class="mt-4 flex justify-end">
                                <?php if (!$is_booked && !$is_past): ?>
                                    <a href="?delete=<?= $slot['id'] ?>&date=<?= urlencode($filter_date) ?>&status=<?= $filter_status ?>" 
                                       onclick="return confirm('Are you sure you want to delete this slot?')"
                                       class="text-red-600 hover:text-red-800 text-sm font-medium">
                                        <i class="fas fa-trash mr-1"></i>
                                        Delete
                                    </a>
                                <?php elseif ($is_booked): ?>
                                    <span class="text-sm text-gray-500">
                                        <i class="fas fa-user mr-1"></i>
                                        Patient booked
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="col-span-full">
                    <div class="bg-white rounded-xl shadow-md p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-calendar-times text-gray-400 text-4xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">No Slots Found</h3>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">
                            You haven't added any availability slots yet. Add your first slot to start receiving appointments.
                        </p>
                        <button onclick="openAddModal()" 
                                class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Add Your First Slot
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Calendar Preview (Optional) -->
        <?php if (count($slots) > 0): ?>
        <div class="mt-8 bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                Weekly Overview
            </h3>
            <div class="grid grid-cols-7 gap-2">
                <?php 
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                $today = new DateTime();
                
                foreach ($days as $index => $day): 
                    $date = clone $today;
                    $date->modify("this $day");
                    
                    // Count slots for this day
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_booked = 1 THEN 1 ELSE 0 END) as booked
                        FROM doctor_slots 
                        WHERE doctor_id = ? AND DATE(slot_time) = ?
                    ");
                    $stmt->execute([$doctor_id, $date->format('Y-m-d')]);
                    $day_stats = $stmt->fetch();
                ?>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-600"><?= $day ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?= $date->format('M d') ?></p>
                        <div class="mt-2">
                            <span class="text-lg font-bold text-blue-600"><?= $day_stats['total'] ?? 0 ?></span>
                            <span class="text-xs text-gray-500">/<?= $day_stats['booked'] ?? 0 ?> booked</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- Add Single Slot Modal -->
<div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Add Availability Slot</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Date & Time <span class="text-red-500">*</span>
                </label>
                <input type="datetime-local" name="slot_time" required 
                       min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Set the date and time for this slot</p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional)
                </label>
                <textarea name="notes" rows="2" 
                          placeholder="e.g., Virtual consultation, Follow-up visit, etc."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_slot"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus-circle mr-2"></i>
                    Add Slot
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Slots Modal -->
<div id="bulkModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Bulk Add Availability Slots</h3>
            <button onclick="closeBulkModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Start Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="start_date" required 
                           min="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        End Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="end_date" required 
                           min="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Start Time <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="start_time" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        End Time <span class="text-red-500">*</span>
                    </label>
                    <input type="time" name="end_time" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Slot Duration (minutes)
                    </label>
                    <select name="slot_duration" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="15">15 minutes</option>
                        <option value="30" selected>30 minutes</option>
                        <option value="45">45 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="90">1.5 hours</option>
                        <option value="120">2 hours</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Break Between Slots
                    </label>
                    <select name="break_duration" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="0">No break</option>
                        <option value="5">5 minutes</option>
                        <option value="10">10 minutes</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (Optional - will apply to all slots)
                </label>
                <textarea name="notes" rows="2" 
                          placeholder="e.g., Virtual consultations only, etc."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            
            <div class="bg-blue-50 rounded-lg p-4 mb-6">
                <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i>
                    Bulk Creation Rules
                </h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Slots will be created for each day between start and end date</li>
                    <li>• Time range will be applied to each day</li>
                    <li>• Duplicate slots will be skipped automatically</li>
                    <li>• You can add up to 100 slots at once</li>
                </ul>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeBulkModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="bulk_add_slots"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Create Slots
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Modals -->
<script>
    // Add Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }
    
    // Bulk Modal functions
    function openBulkModal() {
        document.getElementById('bulkModal').classList.remove('hidden');
    }
    
    function closeBulkModal() {
        document.getElementById('bulkModal').classList.add('hidden');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const bulkModal = document.getElementById('bulkModal');
        
        if (event.target === addModal) {
            closeAddModal();
        }
        if (event.target === bulkModal) {
            closeBulkModal();
        }
    }
    
    // Auto-hide success/error messages
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
    
    // Validate end date is after start date
    document.querySelector('input[name="end_date"]')?.addEventListener('change', function() {
        const startDate = document.querySelector('input[name="start_date"]').value;
        const endDate = this.value;
        
        if (startDate && endDate && endDate < startDate) {
            alert('End date must be after start date');
            this.value = startDate;
        }
    });
</script>

</body>
</html>