<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Handle status update
if (isset($_GET['update_id'], $_GET['status'])) {
    $update_id = (int) $_GET['update_id'];
    $new_status = $_GET['status'];

    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $update_id]);
        
        // If status is approved, also update the slot as booked
        if ($new_status === 'approved') {
            $stmt = $pdo->prepare("
                UPDATE appointments a 
                JOIN doctor_slots s ON a.slot_id = s.id 
                SET s.is_booked = 1 
                WHERE a.id = ?
            ");
            $stmt->execute([$update_id]);
        }
        
        header("Location: appointments.php?success=Appointment updated successfully");
        exit;
    } catch (PDOException $e) {
        header("Location: appointments.php?error=Failed to update appointment");
        exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status_filter'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? '';

// Build query with filters
$query = "
    SELECT 
        a.id,
        s.slot_time AS appointment_datetime,
        a.status,
        a.created_at AS booked_at,
        u.fullname AS patient_name,
        u.email AS patient_email,
        u.id AS patient_id,
        d.fullname AS doctor_name,
        d.specialty AS doctor_specialty,
        dc.category_name AS doctor_category,
        d.id AS doctor_id
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN doctor_categories dc ON d.category_id = dc.id
    JOIN doctor_slots s ON a.slot_id = s.id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(s.slot_time) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY s.slot_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments");
$total_appointments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'approved'");
$approved_count = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM appointments a 
    JOIN doctor_slots s ON a.slot_id = s.id 
    WHERE DATE(s.slot_time) = CURDATE()
");
$today_count = $stmt->fetchColumn();

// Get admin info for sidebar
$stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_name = $stmt->fetchColumn();

// Get counts for badges
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_doctors_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
$total_patients_sidebar = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'");
$pending_appointments_sidebar = $stmt->fetchColumn();

// Get success/error messages
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin | Appointments Management</title>
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
            <a href="patients.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                <span>Patients</span>
                <span class="ml-auto bg-white text-blue-800 px-2 py-0.5 rounded-full text-xs font-bold"><?= $total_patients_sidebar ?></span>
            </a>
            <a href="appointments.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if($pending_appointments_sidebar > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold animate-pulse"><?= $pending_appointments_sidebar ?></span>
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
        
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Appointments Management</h1>
                    <p class="text-sm text-gray-600 mt-1">View and manage all patient appointments</p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-calendar mr-2"></i><?= date('l, F j, Y') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Appointments Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Total Appointments</p>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total_appointments) ?></p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Today's Appointments Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Today</p>
                        <p class="text-3xl font-bold text-purple-600"><?= number_format($today_count) ?></p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-clock text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Pending Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= number_format($pending_count) ?></p>
                    </div>
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <i class="fas fa-hourglass-half text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Approved Card -->
            <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 uppercase tracking-wide">Completed</p>
                        <p class="text-3xl font-bold text-green-600"><?= number_format($approved_count) ?></p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-xl shadow-md mb-6">
            <form method="GET" action="" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-filter absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <select name="status_filter" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="flex-1 relative">
                    <i class="fas fa-calendar absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="date" name="date_filter" value="<?= htmlspecialchars($date_filter) ?>" 
                           class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-all flex-1 sm:flex-none">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="appointments.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition-all flex-1 sm:flex-none text-center">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Appointments List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-calendar-times text-gray-400 text-5xl mb-4"></i>
                                        <p class="text-gray-500 text-lg">No appointments found</p>
                                        <p class="text-gray-400 text-sm mt-2">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $a): 
                                $date = date("M d, Y", strtotime($a['appointment_datetime']));
                                $time = date("h:i A", strtotime($a['appointment_datetime']));
                                $is_past = strtotime($a['appointment_datetime']) < time();
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($a['patient_name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($a['patient_name']) ?></p>
                                                <p class="text-xs text-gray-500">ID: PAT-<?= str_pad($a['patient_id'], 4, '0', STR_PAD_LEFT) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($a['doctor_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($a['doctor_specialty'] ?? 'General') ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($a['doctor_category']) ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-900"><?= $date ?></p>
                                        <p class="text-xs <?= $is_past ? 'text-gray-400' : 'text-blue-600 font-semibold' ?>"><?= $time ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'completed' => 'bg-blue-100 text-blue-800'
                                        ];
                                        $status_color = $status_colors[$a['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full <?= $status_color ?>">
                                            <?= ucfirst($a['status'] ?? 'unknown') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <?php if ($a['status'] === 'pending'): ?>
                                                <a href="?update_id=<?= $a['id'] ?>&status=approved&<?= http_build_query($_GET) ?>" 
                                                   class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center"
                                                   onclick="return confirm('Approve this appointment?')">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </a>
                                                <a href="?update_id=<?= $a['id'] ?>&status=cancelled&<?= http_build_query($_GET) ?>" 
                                                   class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center"
                                                   onclick="return confirm('Cancel this appointment?')">
                                                    <i class="fas fa-times mr-1"></i> Cancel
                                                </a>
                                            <?php elseif ($a['status'] === 'approved'): ?>
                                                <span class="text-xs text-gray-500 flex items-center">
                                                    <i class="fas fa-check-circle text-green-500 mr-1"></i> Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-500">No action</span>
                                            <?php endif; ?>
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
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 text-lg">No appointments found</p>
                        <p class="text-gray-400 text-sm">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($appointments as $a): 
                        $date = date("M d, Y", strtotime($a['appointment_datetime']));
                        $time = date("h:i A", strtotime($a['appointment_datetime']));
                    ?>
                        <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                            <!-- Header with Status -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?= strtoupper(substr($a['patient_name'], 0, 1)) ?>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($a['patient_name']) ?></h3>
                                        <p class="text-xs text-gray-500">PAT-<?= str_pad($a['patient_id'], 4, '0', STR_PAD_LEFT) ?></p>
                                    </div>
                                </div>
                                <?php
                                $status_colors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'completed' => 'bg-blue-100 text-blue-800'
                                ];
                                $status_color = $status_colors[$a['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-3 py-1 text-xs font-medium rounded-full <?= $status_color ?>">
                                    <?= ucfirst($a['status'] ?? 'unknown') ?>
                                </span>
                            </div>
                            
                            <!-- Appointment Details -->
                            <div class="bg-gray-50 rounded-lg p-3 mb-3">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <p class="text-gray-500 text-xs">Doctor</p>
                                        <p class="font-medium"><?= htmlspecialchars($a['doctor_name']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Category</p>
                                        <p class="font-medium"><?= htmlspecialchars($a['doctor_category']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Date</p>
                                        <p class="font-medium"><?= $date ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500 text-xs">Time</p>
                                        <p class="font-medium"><?= $time ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <?php if ($a['status'] === 'pending'): ?>
                                <div class="flex items-center space-x-3">
                                    <a href="?update_id=<?= $a['id'] ?>&status=approved&<?= http_build_query($_GET) ?>" 
                                       class="flex-1 bg-green-600 hover:bg-green-700 text-white text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                       onclick="return confirm('Approve this appointment?')">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </a>
                                    <a href="?update_id=<?= $a['id'] ?>&status=cancelled&<?= http_build_query($_GET) ?>" 
                                       class="flex-1 bg-red-600 hover:bg-red-700 text-white text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                                       onclick="return confirm('Cancel this appointment?')">
                                        <i class="fas fa-times mr-1"></i> Cancel
                                    </a>
                                </div>
                            <?php elseif ($a['status'] === 'approved'): ?>
                                <div class="bg-green-50 text-green-700 p-3 rounded-lg text-sm text-center">
                                    <i class="fas fa-check-circle mr-1"></i> Appointment completed
                                </div>
                            <?php elseif ($a['status'] === 'cancelled'): ?>
                                <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm text-center">
                                    <i class="fas fa-times-circle mr-1"></i> Appointment cancelled
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript for Mobile Menu -->
<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
        alerts.forEach(alert => {
            if (alert.classList.contains('border-l-4')) {
                alert.remove();
            }
        });
    }, 5000);
</script>

</body>
</html>