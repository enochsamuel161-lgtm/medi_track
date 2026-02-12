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

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? 'all';
$availability_filter = $_GET['availability'] ?? 'all';
$sort = $_GET['sort'] ?? 'name';

// Build query with filters - FIXED: Added COALESCE for NULL ratings
$query = "
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
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND status = 'approved') as total_patients,
        COALESCE((SELECT AVG(rating) FROM doctor_ratings WHERE doctor_id = d.id), 0) as rating,
        (SELECT COUNT(*) FROM doctor_ratings WHERE doctor_id = d.id) as review_count
    FROM doctors d
    JOIN doctor_categories dc ON d.category_id = dc.id
    WHERE 1=1
";

$params = [];

// Apply category filter
if ($category_filter !== 'all') {
    $query .= " AND d.category_id = ?";
    $params[] = $category_filter;
}

// Apply availability filter
if ($availability_filter === 'available') {
    $query .= " AND d.status = 'available'";
} elseif ($availability_filter === 'unavailable') {
    $query .= " AND d.status = 'unavailable'";
}

// Apply search filter
if (!empty($search)) {
    $query .= " AND (d.fullname LIKE ? OR d.specialty LIKE ? OR dc.category_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Apply sorting
if ($sort === 'name') {
    $query .= " ORDER BY d.fullname ASC";
} elseif ($sort === 'specialty') {
    $query .= " ORDER BY d.specialty ASC, d.fullname ASC";
} elseif ($sort === 'category') {
    $query .= " ORDER BY dc.category_name ASC, d.fullname ASC";
} elseif ($sort === 'slots') {
    $query .= " ORDER BY available_slots DESC";
} elseif ($sort === 'rating') {
    $query .= " ORDER BY rating DESC, d.fullname ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get all categories for filter dropdown
$categories = $pdo->query("SELECT * FROM doctor_categories ORDER BY category_name")->fetchAll();

// Get total counts
$total_doctors = count($doctors);
$available_count = $pdo->query("SELECT COUNT(*) FROM doctors WHERE status = 'available'")->fetchColumn();

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
    <title>Find Doctors | MediTrack</title>
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
        .doctor-card:hover {
            transform: translateY(-4px);
            transition: transform 0.3s ease;
        }
        .rating-stars i {
            color: #ffc107;
        }
        .filter-active {
            background-color: #ebf8ff;
            border-color: #4299e1;
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
                    <p class="text-2xl font-bold text-white"><?= $available_count ?></p>
                    <p class="text-xs text-blue-200">Available Now</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= $total_doctors ?></p>
                    <p class="text-xs text-blue-200">Total Doctors</p>
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
        
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Find Doctors</span>
                    </div>
                    
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                            <i class="fas fa-user-md text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-gray-800">
                                Find Doctors
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Browse our directory of specialist doctors
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $available_count ?> Available Now
                    </span>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <form method="GET" action="" class="space-y-4">
                <!-- Search Row -->
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Search by doctor name, specialty, or department..." 
                               class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                    </div>
                    <button type="submit" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-colors shadow-md">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                </div>
                
                <!-- Filters Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Category Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Specialty</label>
                        <div class="relative">
                            <select name="category" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white">
                                <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All Specialties</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Availability Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Availability</label>
                        <div class="relative">
                            <select name="availability" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white">
                                <option value="all" <?= $availability_filter === 'all' ? 'selected' : '' ?>>All Doctors</option>
                                <option value="available" <?= $availability_filter === 'available' ? 'selected' : '' ?>>Available Now</option>
                                <option value="unavailable" <?= $availability_filter === 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Sort By -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <div class="relative">
                            <select name="sort" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none bg-white">
                                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="specialty" <?= $sort === 'specialty' ? 'selected' : '' ?>>Specialty</option>
                                <option value="category" <?= $sort === 'category' ? 'selected' : '' ?>>Department</option>
                                <option value="slots" <?= $sort === 'slots' ? 'selected' : '' ?>>Available Slots</option>
                                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Clear Filters -->
                    <div class="flex items-end">
                        <a href="doctors.php" class="w-full px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors text-center">
                            <i class="fas fa-times mr-2"></i>
                            Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Header -->
        <div class="flex items-center justify-between mb-4">
            <p class="text-gray-600">
                <span class="font-semibold text-gray-900"><?= $total_doctors ?></span> doctors found
                <?php if (!empty($search)): ?>
                    matching "<?= htmlspecialchars($search) ?>"
                <?php endif; ?>
            </p>
            <p class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i>
                Updated in real-time
            </p>
        </div>

        <!-- Doctors Grid -->
        <?php if (count($doctors) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100">
                        <!-- Status Bar -->
                        <div class="h-2 <?= $doctor['status'] === 'available' ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-gray-400 to-gray-500' ?>"></div>
                        
                        <!-- Doctor Card Content -->
                        <div class="p-6">
                            <!-- Avatar & Status -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="relative">
                                    <div class="w-20 h-20 <?= $doctor['status'] === 'available' ? 'bg-gradient-to-br from-blue-500 to-blue-600' : 'bg-gradient-to-br from-gray-500 to-gray-600' ?> rounded-2xl flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                                        <?= strtoupper(substr($doctor['fullname'], 0, 1)) ?>
                                    </div>
                                    <?php if ($doctor['status'] === 'available'): ?>
                                        <span class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 border-2 border-white rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Rating -->
                                <div class="text-right">
                                    <div class="flex items-center rating-stars">
                                        <?php
                                        $rating = round($doctor['rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++):
                                        ?>
                                            <i class="fas fa-star <?= $i <= $rating ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= $doctor['review_count'] ?? 0 ?> <?= $doctor['review_count'] == 1 ? 'review' : 'reviews' ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Doctor Info -->
                            <div class="mb-4">
                                <h3 class="text-lg font-bold text-gray-800 mb-1">
                                    Dr. <?= htmlspecialchars($doctor['fullname']) ?>
                                </h3>
                                <p class="text-sm text-blue-600 font-medium mb-1">
                                    <?= htmlspecialchars($doctor['specialty']) ?>
                                </p>
                                <p class="text-xs text-gray-500 mb-2">
                                    <i class="fas fa-tag mr-1"></i>
                                    <?= htmlspecialchars($doctor['category_name']) ?>
                                </p>
                                
                                <!-- Stats -->
                                <div class="flex items-center space-x-3 text-xs text-gray-600 mt-2">
                                    <span class="flex items-center">
                                        <i class="fas fa-user-clock mr-1 text-blue-500"></i>
                                        <?= $doctor['total_patients'] ?? 0 ?> patients
                                    </span>
                                    <span class="flex items-center">
                                        <i class="fas fa-clock mr-1 text-green-500"></i>
                                        <?= $doctor['available_slots'] ?? 0 ?> slots
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center space-x-2">
                                <a href="doctor_profile.php?id=<?= $doctor['id'] ?>" 
                                   class="flex-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors text-center">
                                    <i class="fas fa-id-card mr-1"></i>
                                    Profile
                                </a>
                                <?php if ($doctor['status'] === 'available' && $doctor['available_slots'] > 0): ?>
                                    <a href="book_appointment.php?doctor_id=<?= $doctor['id'] ?>" 
                                       class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors text-center">
                                        <i class="fas fa-calendar-plus mr-1"></i>
                                        Book
                                    </a>
                                <?php else: ?>
                                    <button disabled 
                                            class="flex-1 px-3 py-2 bg-gray-300 text-gray-500 text-sm font-medium rounded-lg cursor-not-allowed text-center">
                                        <i class="fas fa-calendar-times mr-1"></i>
                                        Unavailable
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Quick Contact -->
                            <div class="mt-4 pt-3 border-t border-gray-100 flex justify-between items-center">
                                <a href="mailto:<?= $doctor['email'] ?>" class="text-xs text-gray-500 hover:text-blue-600">
                                    <i class="fas fa-envelope mr-1"></i>
                                    Email
                                </a>
                                <a href="tel:<?= $doctor['phone'] ?>" class="text-xs text-gray-500 hover:text-blue-600">
                                    <i class="fas fa-phone mr-1"></i>
                                    Call
                                </a>
                                <span class="text-xs <?= $doctor['status'] === 'available' ? 'text-green-600' : 'text-red-600' ?>">
                                    <i class="fas fa-circle mr-1 text-xxs"></i>
                                    <?= $doctor['status'] === 'available' ? 'Available' : 'Unavailable' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- No Results Found -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user-md text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Doctors Found</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    We couldn't find any doctors matching your criteria. Try adjusting your filters or search term.
                </p>
                <a href="doctors.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md">
                    <i class="fas fa-redo-alt mr-2"></i>
                    Reset All Filters
                </a>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 text-white">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-calendar-check text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold">Need an appointment quickly?</h4>
                        <p class="text-blue-100 text-sm">Book with our available doctors now</p>
                    </div>
                </div>
                <a href="categories.php" class="mt-4 inline-flex items-center px-4 py-2 bg-white hover:bg-gray-100 text-blue-700 font-medium rounded-lg transition-colors text-sm">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Book Appointment
                </a>
            </div>
            
            <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-question-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold text-gray-800">Need help choosing?</h4>
                        <p class="text-gray-600 text-sm">Our care team is here 24/7</p>
                    </div>
                </div>
                <div class="mt-4 flex space-x-3">
                    <a href="tel:+1234567890" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors text-sm text-center">
                        <i class="fas fa-phone-alt mr-2"></i>
                        Call Now
                    </a>
                    <a href="mailto:support@meditrack.com" class="flex-1 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors text-sm text-center">
                        <i class="fas fa-envelope mr-2"></i>
                        Email
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript -->
<script>
    // Auto-submit filters when dropdowns change
    document.querySelectorAll('select[name="category"], select[name="availability"], select[name="sort"]').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

</body>
</html>