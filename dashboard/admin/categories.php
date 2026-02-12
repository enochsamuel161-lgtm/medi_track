<?php
session_start();
require "../../config/database.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = $_POST['category_name'];
    $description = $_POST['description'] ?? '';
    
    if (!empty($category_name)) {
        $stmt = $pdo->prepare("INSERT INTO doctor_categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([$category_name, $description]);
        header("Location: categories.php?success=Category added successfully");
        exit;
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = $_POST['category_id'];
    $category_name = $_POST['category_name'];
    $description = $_POST['description'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE doctor_categories SET category_name = ?, description = ? WHERE id = ?");
    $stmt->execute([$category_name, $description, $id]);
    header("Location: categories.php?success=Category updated successfully");
    exit;
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    
    // Check if category has doctors
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE category_id = ?");
    $stmt->execute([$id]);
    $doctor_count = $stmt->fetchColumn();
    
    if ($doctor_count > 0) {
        header("Location: categories.php?error=Cannot delete category with assigned doctors");
    } else {
        $stmt = $pdo->prepare("DELETE FROM doctor_categories WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: categories.php?success=Category deleted successfully");
    }
    exit;
}

// Fetch all categories with doctor counts
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM doctors d WHERE d.category_id = c.id) as doctor_count 
    FROM doctor_categories c 
    ORDER BY c.category_name
");
$categories = $stmt->fetchAll();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Doctor Categories</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex flex-col lg:flex-row min-h-screen">

    <!-- SIDEBAR - Same style as other admin pages -->
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
            <a href="appointments.php" class="flex items-center px-4 py-3 text-gray-200 hover:bg-blue-700 rounded-lg transition-all">
                <i class="fas fa-calendar-check w-5 h-5 mr-3"></i>
                <span>Appointments</span>
                <?php if($pending_appointments_sidebar > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-0.5 rounded-full text-xs font-bold"><?= $pending_appointments_sidebar ?></span>
                <?php endif; ?>
            </a>
            <a href="categories.php" class="flex items-center px-4 py-3 bg-blue-700 text-white rounded-lg shadow-md">
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-800">Doctor Categories</h1>
                    <p class="text-sm text-gray-600 mt-1">Manage doctor specializations and categories</p>
                </div>
                <button onclick="openAddModal()" class="mt-4 sm:mt-0 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-lg shadow-lg transition-all transform hover:scale-105">
                    <i class="fas fa-plus-circle mr-2"></i>Add New Category
                </button>
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

        <!-- Categories Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($categories)): ?>
                <div class="col-span-full text-center py-12">
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <i class="fas fa-tags text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Categories Found</h3>
                        <p class="text-gray-500 mb-6">Get started by creating your first doctor category</p>
                        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-all">
                            <i class="fas fa-plus-circle mr-2"></i>Add New Category
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center text-white">
                                        <i class="fas fa-stethoscope text-2xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?= htmlspecialchars($category['category_name']) ?>
                                        </h3>
                                        <span class="text-sm text-gray-500">
                                            ID: CAT-<?= str_pad($category['id'], 3, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <?= $category['doctor_count'] ?> Doctors
                                </span>
                            </div>
                            
                            <?php if (!empty($category['description'])): ?>
                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                    <?= htmlspecialchars($category['description']) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-400 text-sm mb-4 italic">No description provided</p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                <div class="flex space-x-3">
                                    <button onclick="openEditModal(<?= $category['id'] ?>, '<?= htmlspecialchars($category['category_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($category['description'] ?? '', ENT_QUOTES) ?>')" 
                                            class="text-blue-600 hover:text-blue-800 transition-colors"
                                            title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                        <span class="text-xs ml-1">Edit</span>
                                    </button>
                                    
                                    <?php if ($category['doctor_count'] == 0): ?>
                                        <a href="?delete=<?= $category['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this category?')"
                                           class="text-red-600 hover:text-red-800 transition-colors"
                                           title="Delete Category">
                                            <i class="fas fa-trash"></i>
                                            <span class="text-xs ml-1">Delete</span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 cursor-not-allowed" title="Cannot delete category with assigned doctors">
                                            <i class="fas fa-trash"></i>
                                            <span class="text-xs ml-1">Delete</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="doctors.php?category_id=<?= $category['id'] ?>" 
                                   class="text-sm text-blue-600 hover:text-blue-800">
                                    View Doctors <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Category Modal -->
<div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Add New Category</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                <input type="text" name="category_name" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="e.g., Cardiology, Pediatrics">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Describe this category..."></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeAddModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_category"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Edit Category</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="category_id" id="edit_category_id">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                <input type="text" name="category_name" id="edit_category_name" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" id="edit_category_description" rows="3"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" name="edit_category"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('hidden');
        });
    }

    // Add Modal functions
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }
    
    // Edit Modal functions
    function openEditModal(id, name, description) {
        document.getElementById('edit_category_id').value = id;
        document.getElementById('edit_category_name').value = name;
        document.getElementById('edit_category_description').value = description;
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        
        if (event.target === addModal) {
            closeAddModal();
        }
        if (event.target === editModal) {
            closeEditModal();
        }
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