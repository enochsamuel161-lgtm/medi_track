<?php
session_start();

require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

// Fetch patient details for sidebar
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

// Fetch all doctor categories
$stmt = $pdo->query("SELECT * FROM doctor_categories ORDER BY category_name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctor count for each category
foreach($categories as &$category) {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE category_id = ? AND status = 'available'");
    $count_stmt->execute([$category['id']]);
    $category['doctor_count'] = $count_stmt->fetchColumn();
}

// Get total available doctors
$total_doctors = array_sum(array_column($categories, 'doctor_count'));

// Fetch upcoming appointments count for badge
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
    <title>Book Appointment | MediTrack</title>
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
        .category-card:hover .icon-wrapper {
            transform: scale(1.1);
            transition: transform 0.3s ease;
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
                    <p class="text-2xl font-bold text-white"><?= $total_doctors ?></p>
                    <p class="text-xs text-blue-200">Available Doctors</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-white"><?= count($categories) ?></p>
                    <p class="text-xs text-blue-200">Specialties</p>
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
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <a href="index.php" class="hover:text-blue-600">Dashboard</a>
                        <i class="fas fa-chevron-right mx-2 text-xs"></i>
                        <span class="text-gray-700 font-medium">Book Appointment</span>
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-calendar-plus text-blue-600 mr-3"></i>
                        Choose a Specialty
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Select from <?= count($categories) ?> medical specialties to find the right doctor for you
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm flex items-center">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        <span class="text-sm text-gray-600"><?= $total_doctors ?> doctors available</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search/Filter Bar -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-8">
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchCategory" placeholder="Search specialties..." 
                           class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                </div>
                <div class="sm:w-64">
                    <select id="filterDoctors" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                        <option value="all">All Doctors</option>
                        <option value="available">Available Only</option>
                        <option value="5+">5+ Doctors</option>
                        <option value="10+">10+ Doctors</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <?php if (count($categories) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="categoryGrid">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-card bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden group"
                         data-category="<?= strtolower($cat['category_name']) ?>"
                         data-doctors="<?= $cat['doctor_count'] ?>">
                        
                        <!-- Card Header with Icon -->
                        <div class="h-2 bg-gradient-to-r from-blue-500 to-blue-600"></div>
                        
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center">
                                    <!-- Dynamic Icons based on category -->
                                    <div class="icon-wrapper w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center group-hover:bg-blue-100 transition-all duration-300">
                                        <?php
                                        $icon = 'fa-stethoscope';
                                        $category_name = strtolower($cat['category_name']);
                                        if (strpos($category_name, 'cardio') !== false) $icon = 'fa-heart';
                                        else if (strpos($category_name, 'neuro') !== false) $icon = 'fa-brain';
                                        else if (strpos($category_name, 'derma') !== false) $icon = 'fa-allergies';
                                        else if (strpos($category_name, 'pedia') !== false) $icon = 'fa-child';
                                        else if (strpos($category_name, 'ortho') !== false) $icon = 'fa-bone';
                                        else if (strpos($category_name, 'optha') !== false) $icon = 'fa-eye';
                                        else if (strpos($category_name, 'dental') !== false) $icon = 'fa-tooth';
                                        else if (strpos($category_name, 'gyne') !== false) $icon = 'fa-female';
                                        else if (strpos($category_name, 'psych') !== false) $icon = 'fa-smile';
                                        ?>
                                        <i class="fas <?= $icon ?> text-blue-600 text-2xl group-hover:scale-110 transition-transform"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-xl font-bold text-gray-800 group-hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </h3>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Specialty Department
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Doctor Count Badge -->
                                <div class="flex flex-col items-center">
                                    <span class="bg-<?= $cat['doctor_count'] > 0 ? 'blue' : 'gray' ?>-100 text-<?= $cat['doctor_count'] > 0 ? 'blue' : 'gray' ?>-800 text-2xl font-bold px-3 py-1 rounded-lg">
                                        <?= $cat['doctor_count'] ?>
                                    </span>
                                    <span class="text-xs text-gray-500 mt-1">Doctors</span>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <?php if (!empty($cat['description'])): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                    <?= htmlspecialchars($cat['description']) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm mb-4 italic">
                                    Specialized medical care in <?= htmlspecialchars($cat['category_name']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                                <div class="flex items-center text-sm text-gray-500">
                                    <i class="fas fa-user-md mr-1"></i>
                                    <span><?= $cat['doctor_count'] ?> available</span>
                                </div>
                                
                                <a href="book_appointment.php?category_id=<?= $cat['id'] ?>" 
                                   class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-all transform hover:scale-105 shadow-md hover:shadow-lg <?= $cat['doctor_count'] == 0 ? 'opacity-50 pointer-events-none' : '' ?>">
                                    <span>View Doctors</span>
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Availability indicator -->
                        <?php if ($cat['doctor_count'] > 0): ?>
                            <div class="px-6 pb-4">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></span>
                                    <span class="text-xs text-green-600">Accepting new patients</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="px-6 pb-4">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                    <span class="text-xs text-gray-500">Currently unavailable</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-stethoscope text-gray-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">No Specialties Available</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    We're currently setting up our doctor categories. Please check back later to book your appointment.
                </p>
                <a href="index.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                    <i class="fas fa-home mr-2"></i>
                    Return to Dashboard
                </a>
            </div>
        <?php endif; ?>

        <!-- Quick Help Section -->
        <div class="mt-8 bg-blue-50 rounded-xl p-6">
            <div class="flex flex-col sm:flex-row items-center justify-between">
                <div class="flex items-center mb-4 sm:mb-0">
                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-headset text-white text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-lg font-semibold text-gray-800">Need help choosing a specialist?</h4>
                        <p class="text-sm text-gray-600">Our care team is here to guide you 24/7</p>
                    </div>
                </div>
                <a href="tel:+1234567890" class="inline-flex items-center px-6 py-3 bg-white hover:bg-gray-50 text-blue-600 font-medium rounded-lg shadow-sm transition-colors border border-blue-200">
                    <i class="fas fa-phone-alt mr-2"></i>
                    Contact Support
                </a>
            </div>
        </div>

    </main>
</div>

<!-- JavaScript for Search and Filter -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchCategory');
        const filterSelect = document.getElementById('filterDoctors');
        const categoryCards = document.querySelectorAll('.category-card');

        function filterCategories() {
            const searchTerm = searchInput.value.toLowerCase();
            const filterValue = filterSelect.value;

            categoryCards.forEach(card => {
                const categoryName = card.dataset.category || '';
                const doctorCount = parseInt(card.dataset.doctors || '0');
                
                // Search filter
                const matchesSearch = searchTerm === '' || categoryName.includes(searchTerm);
                
                // Doctor count filter
                let matchesCount = true;
                if (filterValue === 'available') {
                    matchesCount = doctorCount > 0;
                } else if (filterValue === '5+') {
                    matchesCount = doctorCount >= 5;
                } else if (filterValue === '10+') {
                    matchesCount = doctorCount >= 10;
                }
                
                // Show/hide card
                if (matchesSearch && matchesCount) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Show "no results" message if needed
            const visibleCards = Array.from(categoryCards).filter(card => card.style.display !== 'none');
            const gridContainer = document.getElementById('categoryGrid');
            let noResultsMsg = document.getElementById('noResultsMsg');
            
            if (visibleCards.length === 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.className = 'col-span-full text-center py-12';
                    noResultsMsg.innerHTML = `
                        <div class="bg-white rounded-xl p-8">
                            <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">No matching specialties</h3>
                            <p class="text-gray-500">Try adjusting your search or filter criteria</p>
                        </div>
                    `;
                    gridContainer.appendChild(noResultsMsg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }

        searchInput.addEventListener('input', filterCategories);
        filterSelect.addEventListener('change', filterCategories);
    });
</script>

</body>
</html>