<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediTrack - Hospital Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <header class="fixed w-full z-50 bg-white bg-opacity-80 backdrop-blur-md shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <!-- Logo / Brand -->
      <div class="flex-shrink-0">
        <a href="#" class="text-2xl font-bold text-blue-600">MediTrack</a>
      </div>

      <!-- Desktop Menu -->
      <nav class="hidden md:flex space-x-6 items-center">
        <a href="#" class="text-gray-700 hover:text-blue-600 font-medium transition">Home</a>
        <a href="#about" class="text-gray-700 hover:text-blue-600 font-medium transition">About</a>
        <a href="#features" class="text-gray-700 hover:text-blue-600 font-medium transition">Features</a>
        <a href="#contact" class="text-gray-700 hover:text-blue-600 font-medium transition">Contact</a>
        <a href="login.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">
          Login
        </a>
      </nav>

      <!-- Mobile Menu Button -->
      <div class="md:hidden flex items-center">
        <button id="menu-btn" class="text-gray-700 focus:outline-none">
          <svg id="menu-open-icon" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
               viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
          <svg id="menu-close-icon" class="h-6 w-6 hidden" fill="none" stroke="currentColor" stroke-width="2"
               viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md transform transition-transform origin-top scale-y-0">
    <a href="#" class="block text-gray-700 hover:text-blue-600 font-medium px-4 py-2 transition">Home</a>
    <a href="#about" class="block text-gray-700 hover:text-blue-600 font-medium px-4 py-2 transition">About</a>
    <a href="#features" class="block text-gray-700 hover:text-blue-600 font-medium px-4 py-2 transition">Features</a>
    <a href="#contact" class="block text-gray-700 hover:text-blue-600 font-medium px-4 py-2 transition">Contact</a>
    <a href="login.html" class="block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition my-2 mx-4">Login</a>
  </div>
</header>

<!-- JavaScript for Mobile Menu -->
<script>
  const menuBtn = document.getElementById('menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  const menuOpenIcon = document.getElementById('menu-open-icon');
  const menuCloseIcon = document.getElementById('menu-close-icon');

  menuBtn.addEventListener('click', () => {
    // Toggle menu
    mobileMenu.classList.toggle('hidden');

    // Animate menu open/close
    if (!mobileMenu.classList.contains('hidden')) {
      mobileMenu.classList.remove('scale-y-0');
      mobileMenu.classList.add('scale-y-100');
    } else {
      mobileMenu.classList.remove('scale-y-100');
      mobileMenu.classList.add('scale-y-0');
    }

    // Toggle icons
    menuOpenIcon.classList.toggle('hidden');
    menuCloseIcon.classList.toggle('hidden');
  });
</script>

<!-- Tailwind transition utilities -->
<style>
  #mobile-menu {
    transform-origin: top;
    transition: transform 0.3s ease-in-out;
  }
  .scale-y-0 {
    transform: scaleY(0);
  }
  .scale-y-100 {
    transform: scaleY(1);
  }
</style>


 <!-- HERO SECTION WITH RESPONSIVE ROTATING VIDEOS -->
<section class="relative w-full h-screen flex items-center justify-center text-center overflow-hidden">
  
  <!-- Background Video -->
  <video id="hero-video" autoplay muted loop playsinline class="absolute top-0 left-0 w-full h-full object-cover">
    <source id="video-source" src="https://www.pexels.com/download/video/5426208/" type="video/mp4">
    Your browser does not support the video tag.
  </video>

  <!-- Overlay -->
  <div class="absolute top-0 left-0 w-full h-full bg-black bg-opacity-50"></div>

  <!-- Hero Content -->
  <div class="relative z-10 px-4 sm:px-6 lg:px-8 text-white max-w-3xl">
    <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold mb-4 sm:mb-6">
      Welcome to <span class="text-blue-400">MediTrack</span>
    </h1>
    <p class="text-md sm:text-lg md:text-xl mb-6 sm:mb-8">
      The ultimate Hospital Management System to manage patients, appointments, and doctors efficiently.  
      Seamless, modern, and secure.
    </p>

    <!-- Buttons -->
    <div class="flex flex-col sm:flex-row justify-center gap-4">
      <a href="login.php" class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
        Get Started
      </a>
      <a href="login.php" class="px-6 py-3 border border-blue-400 text-blue-400 font-semibold rounded-lg hover:bg-blue-50 transition">
        Learn More
      </a>
    </div>
  </div>
</section>

<!-- JavaScript to rotate videos -->
<script>
  const videoSource = document.getElementById('video-source');
  const heroVideo = document.getElementById('hero-video');

  // Array of video URLs
  const videos = [
    "https://www.pexels.com/download/video/5426208/", 
    "https://www.pexels.com/download/video/5426204/", 
    "https://www.pexels.com/download/video/34420192/"
  ];

  let currentVideo = 0;

  setInterval(() => {
    currentVideo = (currentVideo + 1) % videos.length;
    videoSource.src = videos[currentVideo];
    heroVideo.load();
    heroVideo.play();
  }, 10000);

  // Mobile optimization: pause video on small screens
  const checkMobile = () => {
    if (window.innerWidth < 640) {
      heroVideo.pause();
    } else {
      heroVideo.play();
    }
  };

  window.addEventListener('resize', checkMobile);
  checkMobile();
</script>

<!-- Optional Tailwind CSS adjustments for responsiveness -->
<style>
  @media (max-width: 640px) {
    video#hero-video {
      height: 60vh; /* Reduce height on small screens */
    }
    h1 { font-size: 2rem; } /* Adjust text size */
    p { font-size: 0.9rem; }
  }
</style>


<!-- FEATURES SECTION -->
<section id="features" class="bg-gray-50 py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
      Why Choose <span class="text-blue-600">MediTrack</span>
    </h2>
    <p class="text-gray-600 mb-16">
      A modern Hospital Management System designed to simplify hospital operations and improve patient care.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-12">

      <!-- Feature 1 -->
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <div class="flex justify-center mb-4">
          <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Appointment Management</h3>
        <p class="text-gray-500 text-sm">
          Schedule, reschedule, and track appointments with ease, ensuring no patient is missed.
        </p>
      </div>

      <!-- Feature 2 -->
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <div class="flex justify-center mb-4">
          <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a4 4 0 014-4h0a4 4 0 014 4v6"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Patient Records</h3>
        <p class="text-gray-500 text-sm">
          Maintain digital patient records securely, accessible to doctors and admin anytime.
        </p>
      </div>

      <!-- Feature 3 -->
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <div class="flex justify-center mb-4">
          <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Doctor Dashboard</h3>
        <p class="text-gray-500 text-sm">
          Doctors can view appointments, patient lists, and messages in a single, easy-to-use dashboard.
        </p>
      </div>

      <!-- Feature 4 -->
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <div class="flex justify-center mb-4">
          <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 12l9-5-9-5-9 5 9 5zm0 0v10"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Secure Messaging</h3>
        <p class="text-gray-500 text-sm">
          Communicate securely with patients and staff through integrated messaging tools.
        </p>
      </div>

    </div>
  </div>
</section>
<!-- Include AOS CSS in <head> -->
<link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

<!-- ABOUT SECTION -->
<section id="about" class="bg-white py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="lg:grid lg:grid-cols-12 lg:gap-12 lg:items-center">

      <!-- Text Content -->
   <div class="lg:col-span-6 mb-12 lg:mb-0" data-aos="fade-right" data-aos-duration="1000"> 
  <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
    About <span class="text-blue-600">MediTrack</span>
  </h2>

  <p class="text-gray-600 mb-6">
    MediTrack is a cutting-edge Hospital Management System designed to streamline hospital operations.  
    From appointment scheduling, patient record management, to doctor dashboards and secure messaging — everything is in one place.
  </p>

  <p class="text-gray-600 mb-6">
    With MediTrack, hospitals can improve efficiency, enhance patient care, and reduce administrative workload.  
    Our goal is to make hospital management seamless and modern.
  </p>

  <p class="text-gray-600 mb-6">
    Key features include real-time patient monitoring, automated reminders, centralized medical records, and analytics dashboards that help hospital staff make informed decisions quickly.  
    Designed with user-friendly interfaces, MediTrack ensures that both medical staff and administrative personnel can operate the system with ease.
  </p>

  <p class="text-gray-600 mb-6">
    Security and privacy are our top priorities. MediTrack uses advanced encryption to protect sensitive patient data, ensuring compliance with healthcare regulations and providing peace of mind for both hospitals and patients.
  </p>

  <p class="text-gray-600 mb-6">
    Whether you are a small clinic or a large hospital, MediTrack adapts to your workflow, making hospital management efficient, reliable, and stress-free.  
    Experience a smarter way to manage healthcare operations with MediTrack.
  </p>

  <a href="#features" class="inline-block px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition">
    Explore Features
  </a>
</div>
      <!-- Image Content -->
      <div class="lg:col-span-6 flex justify-center" data-aos="fade-left" data-aos-duration="1000">
        <img src="https://images.pexels.com/photos/5355850/pexels-photo-5355850.jpeg" alt="About MediTrack" 
             class="rounded-xl shadow-xl max-w-md object-contain">
      </div>

    </div>
  </div>
</section>
<!-- FAQ SECTION -->
<section id="faq" class="bg-white py-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
        Frequently Asked Questions
      </h2>
      <p class="text-gray-600">
        Have questions? We’ve got answers about MediTrack Hospital Management System.
      </p>
    </div>

    <!-- FAQ Accordion -->
    <div class="max-w-3xl mx-auto space-y-4">

      <!-- FAQ Item 1 -->
      <div class="border border-gray-200 rounded-lg">
        <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-btn focus:outline-none">
          <span class="font-medium text-gray-900">Is MediTrack secure for patient data?</span>
          <svg class="w-6 h-6 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <div class="faq-content px-6 pb-4 hidden text-gray-600">
          Yes! MediTrack encrypts all patient data and follows strict security protocols to protect sensitive information.
        </div>
      </div>

      <!-- FAQ Item 2 -->
      <div class="border border-gray-200 rounded-lg">
        <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-btn focus:outline-none">
          <span class="font-medium text-gray-900">Can doctors access patient records remotely?</span>
          <svg class="w-6 h-6 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <div class="faq-content px-6 pb-4 hidden text-gray-600">
          Yes, doctors with proper authorization can securely access patient records from any device.
        </div>
      </div>

      <!-- FAQ Item 3 -->
      <div class="border border-gray-200 rounded-lg">
        <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-btn focus:outline-none">
          <span class="font-medium text-gray-900">Does MediTrack support appointment scheduling?</span>
          <svg class="w-6 h-6 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <div class="faq-content px-6 pb-4 hidden text-gray-600">
          Absolutely! You can schedule, reschedule, and manage appointments efficiently within the system.
        </div>
      </div>

      <!-- FAQ Item 4 -->
      <div class="border border-gray-200 rounded-lg">
        <button class="w-full text-left px-6 py-4 flex justify-between items-center faq-btn focus:outline-none">
          <span class="font-medium text-gray-900">Is there support for secure messaging?</span>
          <svg class="w-6 h-6 text-gray-500 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
          </svg>
        </button>
        <div class="faq-content px-6 pb-4 hidden text-gray-600">
          Yes, MediTrack provides integrated secure messaging for doctors, staff, and patients.
        </div>
      </div>

    </div>
  </div>

  <!-- FAQ Accordion Script -->
  <script>
    const faqBtns = document.querySelectorAll('.faq-btn');
    faqBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const content = btn.nextElementSibling;
        content.classList.toggle('hidden');
        const icon = btn.querySelector('svg');
        icon.classList.toggle('rotate-45');
      });
    });
  </script>
</section>

<!-- Include AOS JS before </body> -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({
    once: true, // Animation happens only once
  });
</script>
<!-- OUR SERVICES SECTION -->
<section id="services" class="py-20 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Section Title -->
    <div class="text-center mb-12" data-aos="fade-up" data-aos-duration="1000">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
        Our <span class="text-blue-600">Services</span>
      </h2>
      <p class="text-gray-600 max-w-2xl mx-auto">
        MediTrack offers a wide range of services to streamline hospital operations and enhance patient care.  
        Discover how we can make your hospital management seamless.
      </p>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
      <!-- Service 1 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="1000">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M5 8h14M5 16h14M5 4h14M5 20h14" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Appointment Management</h3>
        <p class="text-gray-600 text-sm">
          Schedule and manage patient appointments seamlessly, reducing wait times and improving workflow.
        </p>
      </div>

      <!-- Service 2 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="1200">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-5h4v5m-2-5V9m-4 4h8" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Patient Records</h3>
        <p class="text-gray-600 text-sm">
          Store, access, and manage all patient records securely in one centralized system.
        </p>
      </div>

      <!-- Service 3 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="1400">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Doctor Dashboard</h3>
        <p class="text-gray-600 text-sm">
          Doctors can monitor schedules, patient progress, and communicate securely with staff and patients.
        </p>
      </div>

      <!-- Service 4 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="1600">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Secure Messaging</h3>
        <p class="text-gray-600 text-sm">
          Communicate securely between doctors, staff, and patients with encrypted messaging features.
        </p>
      </div>

      <!-- Service 5 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="1800">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 013 15.382V6.618a2 2 0 011.553-1.894L9 2m6 0l5.447 2.724A2 2 0 0121 6.618v8.764a2 2 0 01-1.553 1.894L15 20m-6 0v-8h6v8" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Analytics & Reports</h3>
        <p class="text-gray-600 text-sm">
          Generate real-time analytics and reports to improve hospital efficiency and patient care.
        </p>
      </div>

      <!-- Service 6 -->
      <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2" data-aos="fade-up" data-aos-duration="2000">
        <div class="flex items-center justify-center mb-4 text-blue-600 text-4xl">
          <!-- Icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18" />
          </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Inventory Management</h3>
        <p class="text-gray-600 text-sm">
          Keep track of medical supplies, equipment, and resources with a simple and organized system.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS SECTION -->
<section id="testimonials" class="py-20 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Section Title -->
    <div class="text-center mb-12" data-aos="fade-up" data-aos-duration="1000">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
        What Our <span class="text-blue-600">Users Say</span>
      </h2>
      <p class="text-gray-600 max-w-2xl mx-auto">
        Hear from hospitals and staff who have transformed their workflow with MediTrack.
      </p>
    </div>

    <!-- Testimonials Carousel / Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
      
      <!-- Testimonial 1 (5 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="1000">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Dr. Emily Watson</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 5 Stars -->
              <span>★★★★★</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "MediTrack has revolutionized how our hospital manages appointments. Everything is now faster and more organized."
        </p>
      </div>

      <!-- Testimonial 2 (4 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="1200">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Dr. John Smith</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 4 Stars -->
              <span>★★★★☆</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "MediTrack simplifies patient management and helps doctors focus on care, not paperwork."
        </p>
      </div>

      <!-- Testimonial 3 (5 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="1400">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Nurse Olivia Brown</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 5 Stars -->
              <span>★★★★★</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "The doctor dashboard and messaging system make our workflow smooth and efficient. Highly recommended!"
        </p>
      </div>

      <!-- Testimonial 4 (4 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="1600">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/men/77.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Dr. Michael Lee</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 4 Stars -->
              <span>★★★★☆</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "Patient records are now easy to access and manage. The interface is clean and responsive on all devices."
        </p>
      </div>

      <!-- Testimonial 5 (5 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="1800">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/women/22.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Nurse Sophia Green</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 5 Stars -->
              <span>★★★★★</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "Analytics and reports make decision-making easier. I love how smooth everything works on mobile and desktop!"
        </p>
      </div>

      <!-- Testimonial 6 (4 stars) -->
      <div class="bg-white rounded-2xl p-6 shadow-lg transform transition duration-500 hover:-translate-y-3 hover:scale-105" data-aos="fade-up" data-aos-duration="2000">
        <div class="flex items-center mb-4">
          <img src="https://randomuser.me/api/portraits/men/12.jpg" alt="User" class="h-12 w-12 rounded-full mr-4">
          <div>
            <h4 class="font-semibold text-gray-900">Dr. Alex Turner</h4>
            <div class="flex text-yellow-400 mt-1">
              <!-- 4 Stars -->
              <span>★★★★☆</span>
            </div>
          </div>
        </div>
        <p class="text-gray-600 text-sm">
          "MediTrack is reliable and fast. The secure messaging feature is a lifesaver for hospital staff."
        </p>
      </div>

    </div>
  </div>
</section>

<!-- Add AOS initialization at the bottom of your page -->
<script>
  AOS.init({
    once: true, // animation happens only once while scrolling
    duration: 1000,
    easing: 'ease-in-out',
  });
</script>


<!-- CONTACT / SUPPORT SECTION -->
<section id="contact" class="py-20 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Section Title -->
    <div class="text-center mb-12" data-aos="fade-up" data-aos-duration="1000">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
        Need <span class="text-blue-600">Support?</span>
      </h2>
      <p class="text-gray-600 max-w-2xl mx-auto">
        For any questions, technical issues, or support requests, reach out to our team.  
        We are here to help you get the most out of MediTrack.
      </p>
    </div>

    <!-- Contact Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">

      <!-- Support Info -->
      <div class="space-y-8" data-aos="fade-right" data-aos-duration="1000">
        <div class="flex items-center space-x-4">
          <div class="text-blue-600">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 12h2a2 2 0 012 2v6H4v-6a2 2 0 012-2h2m4-4v4m0-4V4m0 4H8m4 0h4"/>
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-gray-900">Office Address</h4>
            <p class="text-gray-600">123 MediTrack Ave, Health City, USA</p>
          </div>
        </div>

        <div class="flex items-center space-x-4">
          <div class="text-blue-600">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 12v4l3 3m0 0l3-3m-3 3V8a4 4 0 10-8 0v8m4 4H4a2 2 0 01-2-2V8"/>
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-gray-900">Email for Support</h4>
            <p class="text-gray-600">support@meditrack.com</p>
          </div>
        </div>

        <div class="flex items-center space-x-4">
          <div class="text-blue-600">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2l3 7-3 7H3l3-7-3-7zM13 5h2l3 7-3 7h-2l3-7-3-7z"/>
            </svg>
          </div>
          <div>
            <h4 class="font-semibold text-gray-900">Phone / WhatsApp</h4>
            <p class="text-gray-600">+1 (555) 123-4567</p>
          </div>
        </div>
      </div>

      <!-- Support Form -->
      <div data-aos="fade-left" data-aos-duration="1000">
        <form class="bg-white rounded-2xl p-8 shadow-lg space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <input type="text" placeholder="Your Name" class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 transition" required>
            <input type="email" placeholder="Your Email" class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 transition" required>
          </div>
          <input type="text" placeholder="Subject / Issue" class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 transition" required>
          <textarea rows="5" placeholder="Describe your question or issue..." class="w-full p-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-600 transition" required></textarea>
          <button type="submit" class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition transform hover:scale-105">
            Submit Request
          </button>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- AOS Initialization -->
<script>
  AOS.init({
    once: true,
    duration: 1000,
    easing: 'ease-in-out',
  });
</script>

<!-- FOOTER -->
<footer class="bg-white border-t mt-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="lg:flex lg:justify-between lg:items-start">
      
      <!-- Brand & Description -->
      <div class="mb-8 lg:mb-0">
        <a href="#" class="text-2xl font-bold text-blue-600 mb-2 inline-block">MediTrack</a>
        <p class="text-gray-600 max-w-sm">
          Streamlining hospital management with modern, secure, and easy-to-use software.  
          Manage patients, appointments, and doctors efficiently.
        </p>
      </div>

      <!-- Quick Links -->
      <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
        <div>
          <h3 class="text-gray-900 font-semibold mb-4">Quick Links</h3>
          <ul class="space-y-2">
            <li><a href="#home" class="text-gray-600 hover:text-blue-600">Home</a></li>
            <li><a href="#about" class="text-gray-600 hover:text-blue-600">About</a></li>
            <li><a href="#features" class="text-gray-600 hover:text-blue-600">Features</a></li>
            <li><a href="#contact" class="text-gray-600 hover:text-blue-600">Contact</a></li>
              <li><a href="#services" class="text-gray-600 hover:text-blue-600">Our services</a></li>
          </ul>
        </div>

        <!-- Social Media -->
        <div>
          <h3 class="text-gray-900 font-semibold mb-4">Follow Us</h3>
          <div class="flex space-x-4">
            <a href="#" class="text-gray-600 hover:text-blue-600">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M22.46 6c-.77.35-1.6.59-2.46.69a4.25 4.25 0 001.88-2.34 8.48 8.48 0 01-2.7 1.03 4.22 4.22 0 00-7.19 3.84 12 12 0 01-8.7-4.41 4.22 4.22 0 001.31 5.63 4.2 4.2 0 01-1.91-.53v.05a4.22 4.22 0 003.38 4.13 4.2 4.2 0 01-1.9.07 4.22 4.22 0 003.94 2.93A8.46 8.46 0 012 19.54a11.95 11.95 0 006.29 1.84c7.55 0 11.68-6.26 11.68-11.68 0-.18-.01-.35-.02-.53A8.35 8.35 0 0024 4.56a8.22 8.22 0 01-2.36.65 4.13 4.13 0 001.82-2.27z"/>
              </svg>
            </a>
            <a href="#" class="text-gray-600 hover:text-blue-600">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2.04c-5.5 0-9.96 4.46-9.96 9.96 0 4.41 3.59 8 8 8 1.71 0 3.29-.57 4.55-1.53l5.44 1.43-1.43-5.44A9.96 9.96 0 0022 12c0-5.5-4.46-9.96-9.96-9.96zm0 17.92c-4.37 0-7.96-3.59-7.96-7.96 0-4.37 3.59-7.96 7.96-7.96s7.96 3.59 7.96 7.96c0 4.37-3.59 7.96-7.96 7.96z"/>
              </svg>
            </a>
            <a href="#" class="text-gray-600 hover:text-blue-600">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                <path d="M22 2H2.01C.9 2 0 2.89 0 4v16c0 1.11.89 2 2.01 2H22c1.1 0 2-.89 2-2V4c0-1.11-.9-2-2-2zM8 20H4v-8h4v8zm-2-9.25a2.25 2.25 0 110-4.5 2.25 2.25 0 010 4.5zM20 20h-4v-4c0-1.1-.9-2-2-2s-2 .9-2 2v4h-4v-8h4v1.25c.68-.99 1.85-1.5 3-1.5 2.21 0 4 1.79 4 4v4z"/>
              </svg>
            </a>
          </div>
        </div>
      </div>

    </div>

    <!-- Bottom Copyright -->
    <div class="mt-12 border-t pt-6 text-center text-gray-500 text-sm">
      &copy; 2025 MediTrack. All rights reserved.
    </div>
  </div>
</footer>

</body>
</html>
