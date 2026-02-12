<?php
session_start();
require "../config/database.php";

$error = "";
$success = "";

/* =======================
   SIGNUP LOGIC (PATIENT)
======================= */
if (isset($_POST['signup'])) {
    $name = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';  // ADDED: Phone field
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } else {
        // check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $error = "Email already registered";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // UPDATED: Added phone to INSERT query
            $stmt = $pdo->prepare(
                "INSERT INTO users (fullname, email, phone, password, role, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$name, $email, $phone, $hashed, 'patient']);

            // auto login after signup
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['role'] = 'patient';
            $_SESSION['fullname'] = $name;
            $_SESSION['email'] = $email;

            header("Location: ../dashboard/patient/index.php");
            exit;
        }
    }
}

/* =======================
   LOGIN LOGIC
======================= */
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        // Doctor login logic
        if ($user['role'] === 'doctor') {
            $doctor_stmt = $pdo->prepare("SELECT * FROM doctors WHERE email = ?");
            $doctor_stmt->execute([$email]);
            $doctor = $doctor_stmt->fetch();

            if ($doctor) {
                $_SESSION['user_id'] = $doctor['id'];
                $_SESSION['role'] = 'doctor';
                $_SESSION['doctor_name'] = $doctor['fullname'];
                $_SESSION['email'] = $doctor['email'];

                header("Location: ../dashboard/doctor/index.php");
                exit;
            } else {
                $error = "Doctor record not found.";
            }
        } else {
            // Admin/Patient login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];

            if ($user['role'] === 'admin') {
                header("Location: ../dashboard/admin/index.php");
            } else {
                header("Location: ../dashboard/patient/index.php");
            }
            exit;
        }

    } else {
        $error = "Invalid email or password";
    }
}

// Get current year for copyright
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>MediTrack - Healthcare Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        .bg-gradient-health {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 50%, #1e3a8a 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="bg-gray-100 font-sans antialiased">
    
    <!-- Background Pattern -->
    <div class="fixed inset-0 z-0 opacity-10">
        <div class="absolute inset-0 bg-gradient-health"></div>
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M30 0 L30 60 M0 30 L60 30" stroke="%23ffffff" stroke-width="1" opacity="0.2"/%3E%3C/svg%3E');"></div>
    </div>

    <!-- Navigation Bar -->
    <nav class="relative z-10 bg-white/80 backdrop-blur-md shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg flex items-center justify-center">
                            <i class="fas fa-heartbeat text-white text-xl"></i>
                        </div>
                        <span class="ml-3 text-xl font-bold text-gray-900">Medi<span class="text-blue-600">Track</span></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 hidden sm:block">Your Health, Our Priority</span>
                    <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="relative z-10 min-h-[calc(100vh-64px)] flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="max-w-6xl w-full" data-aos="fade-up" data-aos-duration="1000">
            
            <!-- Welcome Banner - Mobile Only -->
            <div class="block md:hidden bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 mb-6 text-white shadow-xl">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-hospital text-3xl"></i>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-2xl font-bold">Welcome to</h2>
                        <h1 class="text-3xl font-extrabold">MediTrack</h1>
                    </div>
                </div>
                <p class="mt-3 text-blue-100">Your complete healthcare management solution</p>
            </div>

            <!-- Main Card -->
            <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    
                    <!-- LEFT SIDE - Branding & Features (Hidden on mobile, visible on desktop) -->
                    <div class="hidden md:block bg-gradient-health text-white p-12 relative overflow-hidden">
                        <!-- Animated background elements -->
                        <div class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-20 -mt-20"></div>
                        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full -ml-20 -mb-20"></div>
                        
                        <div class="relative z-10 h-full flex flex-col">
                            <div class="flex items-center mb-8">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-heartbeat text-3xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h1 class="text-3xl font-bold">MediTrack</h1>
                                    <p class="text-blue-100">Healthcare HMS</p>
                                </div>
                            </div>
                            
                            <h2 class="text-4xl font-extrabold leading-tight mb-6">
                                Manage Healthcare<br>with Confidence
                            </h2>
                            
                            <p class="text-lg text-blue-100 mb-8">
                                Streamline appointments, patient records, and doctor schedules all in one secure platform.
                            </p>
                            
                            <!-- Feature List -->
                            <div class="space-y-4 mb-8">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-sm"></i>
                                    </div>
                                    <span class="ml-3">Secure patient data management</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-sm"></i>
                                    </div>
                                    <span class="ml-3">Easy appointment scheduling</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-sm"></i>
                                    </div>
                                    <span class="ml-3">Real-time availability tracking</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-sm"></i>
                                    </div>
                                    <span class="ml-3">24/7 access from anywhere</span>
                                </div>
                            </div>
                            
                            <!-- Testimonial -->
                            <div class="mt-auto">
                                <div class="bg-white/10 rounded-xl p-4 backdrop-blur-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-quote-left text-2xl opacity-50"></i>
                                        <p class="ml-2 text-sm italic">
                                            "MediTrack has transformed how we manage our hospital operations. Highly recommended!"
                                        </p>
                                    </div>
                                    <div class="flex items-center mt-3">
                                        <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                            <span class="text-xs font-bold">DR</span>
                                        </div>
                                        <div class="ml-2">
                                            <p class="text-sm font-medium">Dr. Sarah Johnson</p>
                                            <p class="text-xs text-blue-200">Chief of Medicine</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDE - Login/Signup Forms -->
                    <div class="p-8 sm:p-10 lg:p-12">
                        <!-- Tabs -->
                        <div class="flex justify-center mb-8">
                            <div class="bg-gray-100 p-1 rounded-2xl inline-flex">
                                <button id="login-tab" 
                                        class="px-8 py-3 font-semibold rounded-xl transition-all duration-300 bg-white text-blue-600 shadow-md">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                                </button>
                                <button id="signup-tab" 
                                        class="px-8 py-3 font-semibold rounded-xl transition-all duration-300 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-user-plus mr-2"></i>Signup
                                </button>
                            </div>
                        </div>

                        <!-- Error/Success Messages -->
                        <?php if ($error): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg flex items-center justify-between" data-aos="fade-in">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                    <span><?= htmlspecialchars($error) ?></span>
                                </div>
                                <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg flex items-center justify-between" data-aos="fade-in">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                    <span><?= htmlspecialchars($success) ?></span>
                                </div>
                                <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- LOGIN FORM -->
                        <form method="POST" id="login-form" class="space-y-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-blue-500 mr-2"></i>Email Address
                                </label>
                                <input type="email" name="email" placeholder="doctor@hospital.com" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-lock text-blue-500 mr-2"></i>Password
                                </label>
                                <div class="relative">
                                    <input type="password" name="password" id="login-password" placeholder="Enter your password" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white pr-12">
                                    <button type="button" onclick="togglePassword('login-password', 'login-toggle-icon')" 
                                            class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-blue-600">
                                        <i class="fas fa-eye" id="login-toggle-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="checkbox" id="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                                </div>
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 hover:underline">Forgot password?</a>
                            </div>
                            <button type="submit" name="login" 
                                    class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-4 rounded-lg transition-all transform hover:scale-105 flex items-center justify-center">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Login to Dashboard
                            </button>
                        </form>

                        <!-- SIGNUP FORM - UPDATED with Phone Field -->
                        <form method="POST" id="signup-form" class="space-y-5 hidden">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-user text-blue-500 mr-2"></i>Full Name
                                </label>
                                <input type="text" name="fullname" placeholder="John Doe" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-envelope text-blue-500 mr-2"></i>Email Address
                                </label>
                                <input type="email" name="email" placeholder="patient@example.com" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                            </div>
                            
                            <!-- NEW: Phone Number Field -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-phone text-blue-500 mr-2"></i>Phone Number
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <span class="text-gray-500">+1</span>
                                    </div>
                                    <input type="tel" name="phone" id="phone" placeholder="(555) 123-4567" 
                                           class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    For appointment reminders and urgent notifications
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-lock text-blue-500 mr-2"></i>Password
                                </label>
                                <div class="relative">
                                    <input type="password" name="password" id="signup-password" placeholder="Create a password" required 
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white pr-12">
                                    <button type="button" onclick="togglePassword('signup-password', 'signup-toggle-icon')" 
                                            class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 hover:text-blue-600">
                                        <i class="fas fa-eye" id="signup-toggle-icon"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Minimum 8 characters
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-lock text-blue-500 mr-2"></i>Confirm Password
                                </label>
                                <input type="password" name="confirm_password" placeholder="Confirm your password" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition bg-gray-50 focus:bg-white">
                            </div>
                            
                            <!-- Phone Format Helper -->
                            <div class="bg-blue-50 rounded-lg p-3 hidden sm:block">
                                <p class="text-xs text-blue-700 flex items-center">
                                    <i class="fas fa-mobile-alt mr-2"></i>
                                    Format: (555) 123-4567 or 555-123-4567
                                </p>
                            </div>
                            
                            <div class="bg-blue-50 rounded-lg p-3">
                                <p class="text-xs text-blue-700 flex items-center">
                                    <i class="fas fa-shield-alt mr-2"></i>
                                    By signing up, you agree to our Terms of Service and Privacy Policy
                                </p>
                            </div>
                            <button type="submit" name="signup" 
                                    class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold py-3 px-4 rounded-lg transition-all transform hover:scale-105 flex items-center justify-center">
                                <i class="fas fa-user-plus mr-2"></i>
                                Create Account
                            </button>
                        </form>

            <!-- Footer -->
            <div class="mt-8 text-center text-sm text-gray-600">
                <p>© <?= $current_year ?> MediTrack Healthcare System. All rights reserved.</p>
                <p class="mt-1">Version 2.0.0 | Secure • Reliable • Efficient</p>
            </div>
        </div>
    </div>

    <!-- AOS and Custom Scripts -->
    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({ 
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Tab Switching
        const loginTab = document.getElementById('login-tab');
        const signupTab = document.getElementById('signup-tab');
        const loginForm = document.getElementById('login-form');
        const signupForm = document.getElementById('signup-form');

        function switchToLogin() {
            loginForm.classList.remove('hidden');
            signupForm.classList.add('hidden');
            loginTab.classList.add('bg-white', 'text-blue-600', 'shadow-md');
            loginTab.classList.remove('text-gray-500');
            signupTab.classList.remove('bg-white', 'text-blue-600', 'shadow-md');
            signupTab.classList.add('text-gray-500');
        }

        function switchToSignup() {
            signupForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
            signupTab.classList.add('bg-white', 'text-blue-600', 'shadow-md');
            signupTab.classList.remove('text-gray-500');
            loginTab.classList.remove('bg-white', 'text-blue-600', 'shadow-md');
            loginTab.classList.add('text-gray-500');
        }

        loginTab.onclick = switchToLogin;
        signupTab.onclick = switchToSignup;

        // Password Toggle
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Phone number formatting (optional)
        document.getElementById('phone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '(' + value;
                } else if (value.length <= 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                } else {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
                e.target.value = value;
            }
        });

        // Form Validation
        document.querySelector('#signup-form')?.addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            } else if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
            }
        });

        // Auto-hide error messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.bg-red-50, .bg-green-50');
            alerts.forEach(alert => {
                if (alert.classList.contains('border-l-4')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>