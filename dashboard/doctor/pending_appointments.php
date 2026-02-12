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

// Handle appointment approval
if (isset($_GET['approve'])) {
    $appointment_id = (int) $_GET['approve'];
    
    try {
        $pdo->beginTransaction();
        
        // Update appointment status
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'approved' WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$appointment_id, $doctor_id]);
        
        // Mark slot as booked
        $stmt = $pdo->prepare("
            UPDATE doctor_slots ds 
            JOIN appointments a ON ds.id = a.slot_id 
            SET ds.is_booked = 1 
            WHERE a.id = ? AND a.doctor_id = ?
        ");
        $stmt->execute([$appointment_id, $doctor_id]);
        
        $pdo->commit();
        $success = "Appointment approved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error approving appointment: " . $e->getMessage();
    }
    
    header("Location: pending_appointments.php?success=" . urlencode($success) . "&error=" . urlencode($error));
    exit;
}

// Handle appointment rejection
if (isset($_GET['reject'])) {
    $appointment_id = (int) $_GET['reject'];
    
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND doctor_id = ?");
        $stmt->execute([$appointment_id, $doctor_id]);
        $success = "Appointment rejected successfully!";
    } catch (Exception $e) {
        $error = "Error rejecting appointment: " . $e->getMessage();
    }
    
    header("Location: pending_appointments.php?success=" . urlencode($success) . "&error=" . urlencode($error));
    exit;
}

// Handle bulk approval
if (isset($_POST['bulk_approve'])) {
    $appointment_ids = $_POST['appointment_ids'] ?? [];
    
    if (count($appointment_ids) > 0) {
        try {
            $pdo->beginTransaction();
            
            $placeholders = implode(',', array_fill(0, count($appointment_ids), '?'));
            
            // Update appointments
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'approved' 
                WHERE id IN ($placeholders) AND doctor_id = ?
            ");
            $params = array_merge($appointment_ids, [$doctor_id]);
            $stmt->execute($params);
            
            // Update slots
            $stmt = $pdo->prepare("
                UPDATE doctor_slots ds 
                JOIN appointments a ON ds.id = a.slot_id 
                SET ds.is_booked = 1 
                WHERE a.id IN ($placeholders) AND a.doctor_id = ?
            ");
            $stmt->execute(array_merge($appointment_ids, [$doctor_id]));
            
            $pdo->commit();
            $success = count($appointment_ids) . " appointments approved successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error approving appointments: " . $e->getMessage();
        }
    }
    
    header("Location: pending_appointments.php?success=" . urlencode($success ?? '') . "&error=" . urlencode($error ?? ''));
    exit;
}

// Get filter parameters
$filter_date = $_GET['date'] ?? '';
$filter_patient = $_GET['patient'] ?? '';

// Fetch pending appointments
$query = "
    SELECT 
        a.id,
        a.status,
        a.created_at as booked_at,
        ds.slot_time,
        ds.notes as slot_notes,
        u.id as patient_id,
        u.fullname as patient_name,
        u.email as patient_email,
        u.phone as patient_phone,
        u.date_of_birth,
        u.blood_group,
        u.allergies,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id AND doctor_id = ?) as previous_visits,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = ? AND patient_id = u.id) as has_rated
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.status = 'pending'
";

$params = [$doctor_id, $doctor_id, $doctor_id];

if (!empty($filter_date)) {
    $query .= " AND DATE(ds.slot_time) = ?";
    $params[] = $filter_date;
}

if (!empty($filter_patient)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_patient%";
    $params[] = "%$filter_patient%";
}

$query .= " ORDER BY ds.slot_time ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pending_appointments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pending,
        COUNT(DISTINCT patient_id) as unique_patients,
        MIN(ds.slot_time) as earliest,
        MAX(ds.slot_time) as latest
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    WHERE a.doctor_id = ? AND a.status = 'pending'
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

// Get success/error messages from URL
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pending Appointments | MediTrack</title>
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
        .patient-info {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
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
            <div class="mt-6 bg-blue-700/30 rounded-xl p-4">
                <div class="text-center">
                    <p class="text-3xl font-bold text-white"><?= $stats['total_pending'] ?? 0 ?></p>
                    <p class="text-xs text-blue-200">Pending Requests</p>
                    <?php if ($stats['unique_patients'] > 0): ?>
                        <p class="text-xs text-blue-300 mt-1">
                            from <?= $stats['unique_patients'] ?> patients
                        </p>
                    <?php endif; ?>
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
            <a href="pending_appointments.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-xl shadow-md">
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
                        <span class="text-gray-700 font-medium">Pending Appointments</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-hourglass-half text-yellow-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Pending Appointments
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Review and manage appointment requests from patients
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0">
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-clock text-yellow-600 mr-2"></i>
                        <span class="text-sm text-gray-600">
                            <?= $stats['earliest'] ? 'Earliest: ' . date('M d, h:i A', strtotime($stats['earliest'])) : '' ?>
                        </span>
                    </div>
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

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search Patient</label>
                    <input type="text" name="patient" value="<?= htmlspecialchars($filter_patient) ?>" 
                           placeholder="Name or email..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-6 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Apply Filters
                    </button>
                    <a href="pending_appointments.php" class="ml-2 px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <?php if (count($pending_appointments) > 0): ?>
        <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex flex-col sm:flex-row items-center justify-between">
            <div class="flex items-center mb-3 sm:mb-0">
                <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="selectAll" class="ml-2 text-sm text-gray-700">Select All</label>
            </div>
            
            <div class="flex space-x-3">
                <button id="bulkApproveBtn" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-check-circle mr-2"></i>
                    Approve Selected
                </button>
                <button id="bulkRejectBtn"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-times-circle mr-2"></i>
                    Reject Selected
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pending Appointments List -->
        <?php if (count($pending_appointments) > 0): ?>
            <form id="bulkForm" method="POST" action="">
                <div class="space-y-6">
                    <?php 
                    $current_date = '';
                    foreach ($pending_appointments as $apt): 
                        $appointment_date = date('Y-m-d', strtotime($apt['slot_time']));
                        if ($current_date != $appointment_date):
                            $current_date = $appointment_date;
                    ?>
                        <div class="flex items-center mt-6 first:mt-0">
                            <div class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full text-sm font-semibold">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <?= date('l, F d, Y', strtotime($apt['slot_time'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="appointment-card bg-white rounded-2xl shadow-md overflow-hidden border border-yellow-100">
                        <div class="p-6">
                            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between">
                                <!-- Left: Checkbox & Time -->
                                <div class="flex items-start mb-4 lg:mb-0">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="appointment_ids[]" value="<?= $apt['id'] ?>" 
                                               class="appointment-checkbox w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 text-center min-w-[100px]">
                                        <div class="text-2xl font-bold text-gray-800">
                                            <?= date('h:i', strtotime($apt['slot_time'])) ?>
                                        </div>
                                        <div class="text-sm font-semibold text-gray-600">
                                            <?= date('A', strtotime($apt['slot_time'])) ?>
                                        </div>
                                        <?php if (!empty($apt['slot_notes'])): ?>
                                            <p class="text-xs text-gray-500 mt-2 italic">
                                                "<?= htmlspecialchars($apt['slot_notes']) ?>"
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Middle: Patient Information -->
                                <div class="flex-1 lg:ml-6 mb-4 lg:mb-0">
                                    <div class="flex items-start">
                                        <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                            <?= strtoupper(substr($apt['patient_name'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-lg font-bold text-gray-800">
                                                    <?= htmlspecialchars($apt['patient_name']) ?>
                                                </h3>
                                                <?php if ($apt['previous_visits'] > 0): ?>
                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                                        Returning patient (<?= $apt['previous_visits'] ?> visits)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                                        New patient
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Patient Details Grid -->
                                            <div class="grid grid-cols-2 gap-3 mt-3">
                                                <div class="flex items-center text-sm">
                                                    <i class="fas fa-envelope text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600"><?= htmlspecialchars($apt['patient_email']) ?></span>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="fas fa-phone text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600"><?= htmlspecialchars($apt['patient_phone'] ?? 'Not provided') ?></span>
                                                </div>
                                                <?php if ($apt['date_of_birth']): ?>
                                                <div class="flex items-center text-sm">
                                                    <i class="fas fa-birthday-cake text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600">
                                                        <?= date('M d, Y', strtotime($apt['date_of_birth'])) ?>
                                                        (<?= (new DateTime())->diff(new DateTime($apt['date_of_birth']))->y ?> yrs)
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($apt['blood_group']): ?>
                                                <div class="flex items-center text-sm">
                                                    <i class="fas fa-tint text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600">Blood: <?= htmlspecialchars($apt['blood_group']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Allergies -->
                                            <?php if (!empty($apt['allergies'])): ?>
                                            <div class="mt-2 flex items-start">
                                                <i class="fas fa-exclamation-triangle text-red-500 mr-2 mt-1"></i>
                                                <span class="text-sm text-red-600">
                                                    Allergies: <?= htmlspecialchars($apt['allergies']) ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Booking Time -->
                                            <p class="text-xs text-gray-500 mt-2">
                                                <i class="fas fa-clock mr-1"></i>
                                                Requested on <?= date('M d, Y h:i A', strtotime($apt['booked_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: Actions -->
                                <div class="flex flex-col items-start lg:items-end space-y-3">
                                    <div class="flex space-x-2">
                                        <a href="?approve=<?= $apt['id'] ?>" 
                                           onclick="return confirm('Approve this appointment?')"
                                           class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            Approve
                                        </a>
                                        <a href="?reject=<?= $apt['id'] ?>" 
                                           onclick="return confirm('Reject this appointment request?')"
                                           class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition-colors flex items-center">
                                            <i class="fas fa-times-circle mr-2"></i>
                                            Reject
                                        </a>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="patient_details.php?id=<?= $apt['patient_id'] ?>" 
                                           class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-medium transition-colors flex items-center">
                                            <i class="fas fa-user-circle mr-1"></i>
                                            View History
                                        </a>
                                        <a href="messages.php?patient=<?= $apt['patient_id'] ?>" 
                                           class="px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-xs font-medium transition-colors flex items-center">
                                            <i class="fas fa-comment mr-1"></i>
                                            Message
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Urgency Indicator -->
                        <?php 
                        $hours_until = (strtotime($apt['slot_time']) - time()) / 3600;
                        if ($hours_until < 24 && $hours_until > 0): 
                        ?>
                        <div class="bg-red-50 px-6 py-2 border-t border-red-100">
                            <div class="flex items-center text-xs text-red-700">
                                <i class="fas fa-exclamation-circle mr-2 animate-pulse"></i>
                                <span class="font-medium">Urgent:</span>
                                <span class="ml-1">Appointment is less than 24 hours away!</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">All Caught Up!</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    You have no pending appointment requests. Check back later or manage your schedule.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="manage_slots.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md">
                        <i class="fas fa-clock mr-2"></i>
                        Manage Schedule
                    </a>
                    <a href="appointments.php" class="inline-flex items-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        <i class="fas fa-calendar-check mr-2"></i>
                        View Appointments
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Response Tips -->
        <?php if (count($pending_appointments) > 0): ?>
        <div class="mt-8 bg-blue-50 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-lightbulb text-white text-xl"></i>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-4">
                    <h4 class="text-lg font-semibold text-gray-800">Quick Response Tips</h4>
                    <ul class="mt-2 text-sm text-gray-600 space-y-1">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Respond to pending requests promptly - patients appreciate quick confirmation
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Check patient history to see if they've visited before
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                            Use the message feature to communicate with patients if needed
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- JavaScript for Bulk Actions -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('selectAll');
        const appointmentCheckboxes = document.querySelectorAll('.appointment-checkbox');
        const bulkApproveBtn = document.getElementById('bulkApproveBtn');
        const bulkRejectBtn = document.getElementById('bulkRejectBtn');
        const bulkForm = document.getElementById('bulkForm');
        
        // Select All functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                appointmentCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkButtons();
            });
        }
        
        // Individual checkbox change
        appointmentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectAll();
                updateBulkButtons();
            });
        });
        
        // Update Select All checkbox state
        function updateSelectAll() {
            if (!selectAllCheckbox) return;
            
            const allChecked = Array.from(appointmentCheckboxes).every(cb => cb.checked);
            const anyChecked = Array.from(appointmentCheckboxes).some(cb => cb.checked);
            
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = !allChecked && anyChecked;
        }
        
        // Update bulk action buttons state
        function updateBulkButtons() {
            const anyChecked = Array.from(appointmentCheckboxes).some(cb => cb.checked);
            
            if (bulkApproveBtn) {
                bulkApproveBtn.disabled = !anyChecked;
            }
            if (bulkRejectBtn) {
                bulkRejectBtn.disabled = !anyChecked;
            }
        }
        
        // Bulk approve
        if (bulkApproveBtn) {
            bulkApproveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const anyChecked = Array.from(appointmentCheckboxes).some(cb => cb.checked);
                
                if (!anyChecked) {
                    alert('Please select at least one appointment.');
                    return;
                }
                
                if (confirm('Approve the selected appointments?')) {
                    // Create hidden input for bulk action
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_approve';
                    input.value = '1';
                    bulkForm.appendChild(input);
                    bulkForm.submit();
                }
            });
        }
        
        // Bulk reject
        if (bulkRejectBtn) {
            bulkRejectBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const anyChecked = Array.from(appointmentCheckboxes).some(cb => cb.checked);
                
                if (!anyChecked) {
                    alert('Please select at least one appointment.');
                    return;
                }
                
                if (confirm('Reject the selected appointments? This action cannot be undone.')) {
                    // Create hidden input for bulk action
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_reject';
                    input.value = '1';
                    bulkForm.appendChild(input);
                    bulkForm.submit();
                }
            });
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
    });
</script>

</body>
</html>