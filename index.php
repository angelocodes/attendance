<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Face Recognition Attendance System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background-color: #0f172a;
    }
  </style>
</head>
<body class="text-white">

  <!-- Header -->
  <header class="flex items-center justify-between px-6 py-4 bg-gray-900 shadow">
    <h1 class="text-2xl font-bold text-yellow-400">FRSAMS</h1>
    <div>
      <a href="login.php" class="text-white border border-yellow-400 px-4 py-2 rounded mr-2 hover:bg-yellow-400 hover:text-black">Login</a>
       </div>
  </header>

  <!-- Hero Section -->
  <section class="text-center py-20 px-4 bg-gray-800">
    <h2 class="text-4xl font-extrabold text-yellow-400 mb-4">Welcome to the Facial Recognition Students' Attendance Management System</h2>
    <p class="text-lg mb-6">This system enables fast and secure attendance tracking using facial recognition technology. Designed for accurate monitoring in academic environments.</p>
    <a href="login.php" class="bg-yellow-400 text-black px-6 py-3 rounded hover:bg-yellow-300">Get Started</a>
  </section>

  <!-- Features Section -->
  <section class="py-16 px-6">
    <h3 class="text-3xl font-bold text-center text-yellow-400 mb-12">System Features</h3>
    <div class="grid md:grid-cols-3 gap-8">

      <!-- Feature Box -->
      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M15 10l4.553 2.276a1 1 0 010 1.448L15 16m0-6v6m0-6H5a2 2 0 00-2 2v2a2 2 0 002 2h10"></path>
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Student Registration</h4>
        <p>Register students and capture face data for attendance recognition.</p>
      </div>

      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M15 9a3 3 0 11-6 0 3 3 0 016 0zm6 11a9 9 0 10-18 0h18z" />
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Face Recognition</h4>
        <p>Use the webcam to take attendance based on real-time face detection.</p>
      </div>

      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M3 5h18M9 3v2m6-2v2M5 8h14v11a2 2 0 01-2 2H7a2 2 0 01-2-2V8z" />
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Attendance Reports</h4>
        <p>Track who is present or absent with date-wise records and summaries.</p>
      </div>

      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M7 8h10M7 12h6m-6 4h10M5 6a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6z" />
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Course & Session Management</h4>
        <p>Assign students to courses, semesters, and sessions easily.</p>
      </div>

      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M5.121 17.804A9 9 0 1117.804 5.121 9 9 0 015.121 17.804z" />
            <path d="M12 7v5l3 3" />
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Admin & Lecturer Roles</h4>
        <p>Admin manages system settings, lecturers take attendance and view stats.</p>
      </div>

      <div class="bg-gray-700 p-6 rounded-xl shadow text-center">
        <div class="flex justify-center mb-3">
          <svg class="w-10 h-10 text-yellow-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M12 15v2m-6 4h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>
        <h4 class="text-xl font-semibold text-yellow-300 mb-2">Secure Login</h4>
        <p>Role-based login system with password authentication and session control.</p>
      </div>

    </div>
  </section>

  <!-- About Section -->
  <section class="bg-gray-800 py-12 px-6 text-center">
    <h3 class="text-2xl font-bold text-yellow-400 mb-4">About the System</h3>
    <p>This system was built as a final year university project to automate attendance using facial recognition. It reduces manual workload and improves accuracy in tracking student presence.</p>
  </section>

  <!-- Footer -->
  <footer class="text-center py-6 bg-gray-900 text-gray-400">
    &copy; 2025 Soroti University - Final Year Project by Milly
  </footer>

</body>
</html>
