<?php
session_start();
require "../../config/database.php";

// Ensure patient is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../../public/login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$doctor_id = $_GET['doctor_id'] ?? 0;
$appointment_id = $_GET['appointment_id'] ?? 0;

// Verify this patient had a completed appointment with this doctor
$stmt = $pdo->prepare("
    SELECT a.*, d.fullname as doctor_name, d.specialty, dc.category_name,
           ds.slot_time, u.fullname as patient_name
    FROM appointments a
    JOIN doctor_slots ds ON a.slot_id = ds.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN doctor_categories dc ON d.category_id = dc.id
    JOIN users u ON a.patient_id = u.id
    WHERE a.patient_id = ? AND a.doctor_id = ? AND a.id = ?
    AND ds.slot_time < NOW() AND a.status = 'approved'
");
$stmt->execute([$patient_id, $doctor_id, $appointment_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    header("Location: my_appointments.php?error=invalid_appointment");
    exit;
}

// Check if already rated
$stmt = $pdo->prepare("SELECT id FROM doctor_ratings WHERE patient_id = ? AND doctor_id = ? AND appointment_id = ?");
$stmt->execute([$patient_id, $doctor_id, $appointment_id]);
$existing_rating = $stmt->fetch();

if ($existing_rating) {
    header("Location: doctor_profile.php?id=$doctor_id&msg=already_rated");
    exit;
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'] ?? 0;
    $review = trim($_POST['review'] ?? '');
    $wait_time = $_POST['wait_time'] ?? null;
    $doctor_friendliness = $_POST['doctor_friendliness'] ?? null;
    $staff_friendliness = $_POST['staff_friendliness'] ?? null;
    $clarity = $_POST['clarity'] ?? null;
    
    if ($rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctor_ratings 
                (doctor_id, patient_id, appointment_id, rating, review, wait_time, 
                 doctor_friendliness, staff_friendliness, clarity, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $doctor_id, $patient_id, $appointment_id, $rating, $review,
                $wait_time, $doctor_friendliness, $staff_friendliness, $clarity
            ]);
            
            header("Location: doctor_profile.php?id=$doctor_id&success=rated");
            exit;
        } catch (PDOException $e) {
            $error = "Failed to submit review. Please try again.";
        }
    } else {
        $error = "Please select a rating.";
    }
}

// Get patient details for sidebar
$stmt = $pdo->prepare("SELECT fullname, email, phone FROM users WHERE id = ?");
$stmt->execute([$patient_id]);
$user = $stmt->fetch();
$fullname = $user['fullname'] ?? 'Patient';
$initials = strtoupper(substr($fullname, 0, 1) . (strpos($fullname, ' ') ? substr($fullname, strpos($fullname, ' ') + 1, 1) : substr($fullname, 1, 1)));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Rate Doctor | MediTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .rating-input {
            display: none;
        }
        .rating-label {
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-label:hover,
        .rating-label:hover ~ .rating-label {
            color: #fbbf24;
        }
        .rating-input:checked ~ .rating-label {
            color: #fbbf24;
        }
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating .rating-label {
            font-size: 2rem;
            padding: 0 0.1rem;
            color: #d1d5db;
        }
        .star-rating .rating-label:hover,
        .star-rating .rating-label:hover ~ .rating-label,
        .star-rating .rating-input:checked ~ .rating-label {
            color: #fbbf24;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full">
            <!-- Back Button -->
            <div class="mb-4">
                <a href="doctor_profile.php?id=<?= $doctor_id ?>" class="inline-flex items-center text-gray-600 hover:text-blue-600">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Doctor Profile
                </a>
            </div>
            
            <!-- Main Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8 text-white">
                    <div class="flex items-center">
                        <div class="w-20 h-20 bg-white rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-star text-yellow-400 text-4xl"></i>
                        </div>
                        <div class="ml-4">
                            <h1 class="text-2xl font-bold">Rate Your Experience</h1>
                            <p class="text-blue-100 mt-1">Help others by sharing your feedback</p>
                        </div>
                    </div>
                </div>
                
                <!-- Doctor Info -->
                <div class="px-6 py-4 bg-gray-50 border-b flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                        <?= strtoupper(substr($appointment['doctor_name'], 0, 1)) ?>
                    </div>
                    <div class="ml-3">
                        <p class="font-semibold text-gray-800">Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($appointment['specialty']) ?> • <?= htmlspecialchars($appointment['category_name']) ?></p>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-calendar mr-1"></i>
                            Appointment on <?= date('M d, Y', strtotime($appointment['slot_time'])) ?>
                        </p>
                    </div>
                </div>
                
                <!-- Error Message -->
                <?php if (isset($error)): ?>
                    <div class="mx-6 mt-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Rating Form -->
                <form method="POST" class="p-6">
                    <!-- Overall Rating -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-800 mb-3">
                            Overall Experience <span class="text-red-500">*</span>
                        </label>
                        <div class="star-rating mb-2">
                            <input type="radio" name="rating" value="5" id="star5" class="rating-input" required>
                            <label for="star5" class="rating-label"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="4" id="star4" class="rating-input">
                            <label for="star4" class="rating-label"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="3" id="star3" class="rating-input">
                            <label for="star3" class="rating-label"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="2" id="star2" class="rating-input">
                            <label for="star2" class="rating-label"><i class="fas fa-star"></i></label>
                            
                            <input type="radio" name="rating" value="1" id="star1" class="rating-input">
                            <label for="star1" class="rating-label"><i class="fas fa-star"></i></label>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Select your rating</p>
                    </div>
                    
                    <!-- Detailed Ratings -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Wait Time
                            </label>
                            <select name="wait_time" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="5">Excellent - No wait</option>
                                <option value="4">Good - Less than 10 min</option>
                                <option value="3">Average - 10-20 min</option>
                                <option value="2">Long - 20-30 min</option>
                                <option value="1">Very Long - 30+ min</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Doctor Friendliness
                            </label>
                            <select name="doctor_friendliness" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="5">Excellent - Very friendly</option>
                                <option value="4">Good - Friendly</option>
                                <option value="3">Average - Professional</option>
                                <option value="2">Poor - Rushed</option>
                                <option value="1">Very Poor - Rude</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Staff Friendliness
                            </label>
                            <select name="staff_friendliness" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="5">Excellent - Very helpful</option>
                                <option value="4">Good - Helpful</option>
                                <option value="3">Average - Neutral</option>
                                <option value="2">Poor - Unhelpful</option>
                                <option value="1">Very Poor - Rude</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Explanation Clarity
                            </label>
                            <select name="clarity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="5">Excellent - Very clear</option>
                                <option value="4">Good - Clear</option>
                                <option value="3">Average - Somewhat clear</option>
                                <option value="2">Poor - Confusing</option>
                                <option value="1">Very Poor - No explanation</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Written Review -->
                    <div class="mb-8">
                        <label class="block text-lg font-semibold text-gray-800 mb-3">
                            Write Your Review (Optional)
                        </label>
                        <textarea 
                            name="review" 
                            rows="5" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Share your experience with this doctor... What did you like? How was the treatment? Would you recommend them?"
                        ></textarea>
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Your review will be public and help other patients make informed decisions.
                        </p>
                    </div>
                    
                    <!-- Tips for Writing a Good Review -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-8">
                        <h4 class="font-semibold text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-lightbulb mr-2"></i>
                            Tips for writing a helpful review
                        </h4>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Be specific about your experience</li>
                            <li>• Mention what went well and what could be improved</li>
                            <li>• Keep it respectful and constructive</li>
                            <li>• Focus on the medical care and service</li>
                        </ul>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" 
                                class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-6 rounded-lg transition-all transform hover:scale-105">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Submit Review
                        </button>
                        <a href="doctor_profile.php?id=<?= $doctor_id ?>" 
                           class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition-colors text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Review Guidelines -->
            <div class="mt-6 bg-white rounded-xl p-4 shadow-sm">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-green-600 mt-1 mr-3"></i>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Review Guidelines</p>
                        <p class="text-xs text-gray-500 mt-1">
                            We believe in authentic reviews. Please ensure your review is based on your personal experience.
                            Reviews that contain inappropriate content, personal information, or spam will be removed.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>