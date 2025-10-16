<?php
session_start();
require_once '../db.php';

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Fetch theme color
$theme_color = '#3b82f6';
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
$stmt->execute();
$stmt->bind_result($theme_color_db);
if ($stmt->fetch() && preg_match('/^#[0-9A-Fa-f]{6}$/', $theme_color_db)) {
    $theme_color = $theme_color_db;
}
$stmt->close();

// Fetch initial assignments for scheduling
$assign_stmt = $conn->prepare("SELECT la.assignment_id, cu.unit_name FROM lecturer_assignments la JOIN course_units cu ON la.unit_id = cu.unit_id WHERE la.lecturer_id = ?");
$assign_stmt->bind_param("i", $lecturer_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$assign_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="../face-api.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root { --theme-color: <?= htmlspecialchars($theme_color) ?>; }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .border-theme { border-color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
        .transition-all { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-gray-900 text-white" x-data="dashboard">
    <?php include 'navbar.php'; ?>

    <main class="container mx-auto flex justify-center pt-32">
        <div class="w-full max-w-4xl">
            <!-- Dashboard Cards -->
            <div x-show="tab === 'dashboard'" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 animate__animated animate__fadeIn">
                <div class="bg-gray-800 shadow rounded-lg p-6 hover:shadow-lg transition-all border border-gray-700">
                    <h2 class="text-lg font-semibold mb-2 text-yellow-400">Pending Notifications</h2>
                    <p class="text-3xl font-bold text-white" x-text="notificationCount"></p>
                </div>
                <div class="bg-gray-800 shadow rounded-lg p-6 hover:shadow-lg transition-all border border-gray-700">
                    <h2 class="text-lg font-semibold mb-2 text-yellow-400">Upcoming Sessions</h2>
                    <p class="text-3xl font-bold text-white" x-text="Object.values(scheduledSessions).flat().length"></p>
                </div>
                <div class="bg-gray-800 shadow rounded-lg p-6 hover:shadow-lg transition-all border border-gray-700">
                    <h2 class="text-lg font-semibold mb-2 text-yellow-400">My Units</h2>
                    <p class="text-3xl font-bold text-white" x-text="myUnits.length"></p>
                </div>
                <div class="bg-gray-800 shadow rounded-lg p-6 hover:shadow-lg transition-all border border-gray-700">
                    <h2 class="text-lg font-semibold mb-2 text-yellow-400">Attendance Today</h2>
                    <p class="text-3xl font-bold text-white" x-text="attendanceHistory.filter(h => h.session_date === new Date().toISOString().split('T')[0]).length"></p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-gray-800 shadow rounded-lg p-6 animate__animated animate__fadeIn border border-gray-700">
                <div class="flex space-x-4 border-b mb-4 overflow-x-auto">
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'dashboard'}" @click="switchTab('dashboard')">Dashboard</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'face-recognition'}" @click="switchTab('face-recognition')">Face Recognition</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'schedule-session'}" @click="switchTab('schedule-session')">Schedule Session</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'attendance-history'}" @click="switchTab('attendance-history')">Attendance History</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'my-units'}" @click="switchTab('my-units')">My Units</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'profile'}" @click="switchTab('profile')">Profile</button>
                    <button class="pb-2 border-b-2 whitespace-nowrap" :class="{'border-theme text-theme font-bold': tab === 'notifications'}" @click="switchTab('notifications')">Notifications</button>
                </div>

                <!-- Face Recognition Tab -->
                <div x-show="tab === 'face-recognition'" class="transition-all animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Live Face Recognition Attendance</h2>
                    <div x-show="loadingSessions" class="text-center py-4">Loading sessions...</div>
                    <div x-show="!loadingSessions && !selectedSession" class="max-w-lg mx-auto">
                        <h3 class="font-semibold mb-2 text-white">Select a Scheduled Session</h3>
                        <template x-for="(sessions, unit_name) in scheduledSessions">
                            <div class="mb-4">
                                <h4 class="text-lg font-medium text-white" x-text="unit_name"></h4>
                                <ul class="space-y-2">
                                    <template x-for="session in sessions">
                                        <li class="p-2 border border-gray-600 rounded cursor-pointer hover:bg-gray-700 animate__animated animate__pulse text-white" @click="selectSession(session)">
                                            <p x-text="`${session.session_date} | ${session.start_time} - ${session.end_time} | Venue: ${session.venue}`"></p>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                    <div x-show="selectedSession" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <video id="video" autoplay muted playsinline class="w-full rounded-lg shadow-lg border border-gray-300"></video>
                            <button @click="toggleRecognition" class="absolute bottom-4 left-4 px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse">
                                <span x-text="isRecognizing ? 'Pause' : 'Resume'"></span>
                            </button>
                            <button @click="selectedSession = null; stopRecognition()" class="absolute bottom-4 right-4 px-4 py-2 bg-gray-500 text-white rounded hover:bg-opacity-90 animate__animated animate__pulse">Back to Sessions</button>
                            <p class="mt-2 text-gray-500 text-center" x-text="faceError || 'Webcam live feed detecting students...'"></p>
                        </div>
                        <div>
                            <h3 class="font-semibold mb-2 text-center text-white">Detected Students</h3>
                            <ul id="detected-students" class="list-disc pl-6 text-white space-y-2"></ul>
                        </div>
                    </div>
                </div>

                <!-- Schedule Session Tab -->
                <div x-show="tab === 'schedule-session'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Schedule New Session</h2>
                    <form @submit.prevent="scheduleSession" class="space-y-4">
                        <select x-model="schedule.assignment_id" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white rounded focus:ring-theme">
                            <option value="">Select Unit</option>
                            <?php foreach ($assignments as $a): ?>
                                <option value="<?= $a['assignment_id'] ?>"><?= htmlspecialchars($a['unit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" x-model="schedule.session_date" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white rounded focus:ring-theme" />
                        <input type="time" x-model="schedule.start_time" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white rounded focus:ring-theme" />
                        <input type="time" x-model="schedule.end_time" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white rounded focus:ring-theme" />
                        <input type="text" x-model="schedule.venue" placeholder="Venue" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded focus:ring-theme" />
                        <button type="submit" class="w-full px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse" :disabled="loadingSchedule">Schedule</button>
                    </form>
                    <p x-text="scheduleMsg" class="mt-2 text-center" :class="{'text-green-600': scheduleSuccess, 'text-red-600': !scheduleSuccess}"></p>
                </div>

                <!-- Attendance History Tab (with manual marking) -->
                <div x-show="tab === 'attendance-history'" class="transition-all animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Attendance History</h2>
                    <div class="mb-4 flex flex-col md:flex-row md:space-x-4 space-y-2 md:space-y-0 items-center">
                        <select x-model="selectedSessionId" @change="loadAttendanceHistory" class="w-full md:w-1/2 p-2 border border-gray-600 bg-gray-700 text-white rounded focus:ring-theme">
                            <option value="">All Sessions</option>
                            <template x-for="(sessions, unit_name) in scheduledSessions">
                                <optgroup :label="unit_name">
                                    <template x-for="session in sessions">
                                        <option :value="session.session_id" x-text="`${session.session_date} | ${session.start_time} - ${session.end_time} | ${session.venue}`"></option>
                                    </template>
                                </optgroup>
                            </template>
                        </select>
                        <div class="flex space-x-2">
                        <input type="date" x-model="historyFromDate" class="p-2 border border-gray-600 bg-gray-700 text-white rounded">
                        <input type="date" x-model="historyToDate" class="p-2 border border-gray-600 bg-gray-700 text-white rounded">
                            <button @click="loadAttendanceHistory" class="px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse">Filter</button>
                            <button @click="exportAttendance" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 animate__animated animate__pulse">Export CSV</button>
                        </div>
                    </div>
                    <div x-show="loadingHistory" class="text-center py-4">Loading...</div>
                    <table x-show="!loadingHistory" class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-white">Mark</th>
                                <th class="px-6 py-3 text-left text-white">Student</th>
                                <th class="px-6 py-3 text-left text-white">Unit</th>
                                <th class="px-6 py-3 text-left text-white">Status</th>
                                <th class="px-6 py-3 text-left text-white">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700" x-html="historyTableHtml"></tbody>
                    </table>
                    <p x-text="historyMsg" class="mt-2 text-center" :class="{'text-green-600': historySuccess, 'text-red-600': !historySuccess}"></p>
                </div>

                <!-- My Units Tab -->
                <div x-show="tab === 'my-units'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">My Course Units</h2>
                    <div x-show="loadingMyUnits" class="text-center py-4">Loading...</div>
                    <div x-show="!loadingMyUnits" class="space-y-4">
                        <template x-for="unit in myUnits">
                            <div class="p-4 border border-gray-600 rounded animate__animated animate__bounceIn bg-gray-800">
                                <h3 class="font-semibold mb-2 cursor-pointer text-white" @click="unit.expanded = !unit.expanded" x-text="unit.unit_name"></h3>
                                <div x-show="unit.expanded">
                                    <p class="text-gray-300">Enrolled Students:</p>
                                    <p class="text-white" x-text="unit.students || 'No students enrolled'"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Profile Tab -->
                <div x-show="tab === 'profile'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">My Profile</h2>
                    <div x-show="loadingProfile" class="text-center py-4">Loading...</div>
                    <form x-show="!loadingProfile" @submit.prevent="updateProfile" class="space-y-4">
                        <input type="email" x-model="profile.email" placeholder="Email" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded focus:ring-theme" />
                        <input type="text" x-model="profile.phone_number" placeholder="Phone Number" required class="w-full p-2 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded focus:ring-theme" />
                        <input type="password" x-model="profile.password" placeholder="New Password (leave blank to keep current)" class="w-full p-2 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded focus:ring-theme" />
                        <input type="password" x-model="profile.password_confirm" placeholder="Confirm New Password" class="w-full p-2 border border-gray-600 bg-gray-700 text-white placeholder-gray-400 rounded focus:ring-theme" />
                        <p class="text-gray-300">Username: <span class="text-white" x-text="profile.username"></span></p>
                        <p class="text-gray-300">Staff Number: <span class="text-white" x-text="profile.staff_number"></span></p>
                        <p class="text-gray-300">Rank: <span class="text-white" x-text="profile.rank"></span></p>
                        <button type="submit" class="w-full px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse" :disabled="loadingUpdateProfile">Update</button>
                    </form>
                    <p x-text="profileMsg" class="mt-2 text-center" :class="{'text-green-600': profileSuccess, 'text-red-600': !profileSuccess}"></p>
                </div>

                <!-- Notifications Tab -->
                <div x-show="tab === 'notifications'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Notifications</h2>
                    <div x-show="loadingNotifications" class="text-center py-4">Loading...</div>
                    <ul class="space-y-4" x-show="!loadingNotifications">
                        <template x-for="notif in notifications">
                            <li class="p-4 border border-gray-600 rounded bg-gray-800 hover:bg-gray-700 transition-all cursor-pointer animate__animated animate__bounceIn" @click="markRead(notif.notification_id)" :class="{'opacity-50': notif.is_read}">
                                <p class="text-white" x-text="notif.message"></p>
                                <p class="text-xs text-gray-300" x-text="notif.created_at"></p>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
    const MODEL_URL = '../models';
    document.addEventListener('alpine:init', () => {
        Alpine.data('dashboard', () => ({
            tab: 'dashboard',
            notificationCount: 0,
            scheduledSessions: {},
            selectedSession: null,
            selectedSessionId: '',
            myUnits: [],
            loadingMyUnits: false,
            profile: {},
            loadingProfile: false,
            loadingUpdateProfile: false,
            profileMsg: '',
            profileSuccess: true,
            attendanceHistory: [],
            notifications: [],
            historyTableHtml: '',
            historyFromDate: '',
            historyToDate: '',
            historyMsg: '',
            historySuccess: true,
            loadingHistory: false,
            loadingNotifications: false,
            loadingSessions: false,
            schedule: { assignment_id: '', session_date: '', start_time: '', end_time: '', venue: '' },
            scheduleMsg: '',
            scheduleSuccess: true,
            loadingSchedule: false,
            labeledDescriptors: [],
            isRecognizing: true,
            faceError: '',
            recognitionInterval: null,
            videoStream: null,

            async switchTab(newTab) {
                console.log('Switching to tab:', newTab); // Debug: Log tab switching
                this.tab = newTab;
                if (newTab === 'face-recognition' || newTab === 'attendance-history') {
                    console.log('Loading scheduled sessions for tab:', newTab); // Debug: Log session loading
                    await this.loadScheduledSessions();
                }
                if (newTab === 'attendance-history') await this.loadAttendanceHistory();
                if (newTab === 'notifications') await this.loadNotifications();
                if (newTab === 'my-units') await this.loadMyUnits();
                if (newTab === 'profile') await this.loadProfile();
            },

            async loadScheduledSessions() {
                this.loadingSessions = true;
                try {
                    const res = await axios.get('api.php?action=get_scheduled_sessions');
                    console.log('API Response:', res.data); // Debug: Log API response
                    this.scheduledSessions = res.data;
                    console.log('Scheduled Sessions set to:', this.scheduledSessions); // Debug: Log component data
                } catch (err) {
                    console.error('Error loading sessions:', err);
                    this.historyMsg = 'Error loading sessions';
                    this.historySuccess = false;
                }
                this.loadingSessions = false;
            },

            async loadMyUnits() {
                this.loadingMyUnits = true;
                try {
                    const res = await axios.get('api.php?action=get_my_units');
                    this.myUnits = res.data.map(u => ({ ...u, expanded: false }));
                } catch (err) {
                    console.error(err);
                }
                this.loadingMyUnits = false;
            },

            async loadProfile() {
                this.loadingProfile = true;
                try {
                    const res = await axios.get('api.php?action=get_profile');
                    this.profile = { ...res.data, password: '', password_confirm: '' };
                } catch (err) {
                    console.error(err);
                    this.profileMsg = 'Error loading profile';
                    this.profileSuccess = false;
                }
                this.loadingProfile = false;
            },

            async updateProfile() {
                this.loadingUpdateProfile = true;
                this.profileMsg = '';
                if (this.profile.password && (this.profile.password.length < 8 || this.profile.password !== this.profile.password_confirm)) {
                    this.profileMsg = 'Password must be at least 8 characters and match confirmation';
                    this.profileSuccess = false;
                    this.loadingUpdateProfile = false;
                    return;
                }
                try {
                    const res = await axios.post('api.php', { action: 'update_profile', email: this.profile.email, phone_number: this.profile.phone_number, password: this.profile.password });
                    this.profileMsg = res.data.message;
                    this.profileSuccess = res.data.success;
                    if (res.data.success) {
                        this.profile.password = '';
                        this.profile.password_confirm = '';
                        setTimeout(() => this.profileMsg = '', 3000);
                    }
                } catch (err) {
                    this.profileMsg = 'Error updating profile';
                    this.profileSuccess = false;
                }
                this.loadingUpdateProfile = false;
            },

            async loadNotifications() {
                this.loadingNotifications = true;
                try {
                    const res = await axios.get('api.php?action=get_notifications');
                    this.notifications = res.data;
                    this.notificationCount = this.notifications.filter(n => !n.is_read).length;
                } catch (err) {
                    console.error(err);
                }
                this.loadingNotifications = false;
            },

            async markRead(id) {
                try {
                    const res = await axios.post('api.php', { action: 'mark_notification_read', notification_id: id });
                    if (res.data.success) {
                        const notif = this.notifications.find(n => n.notification_id === id);
                        if (notif) notif.is_read = 1;
                        this.notificationCount--;
                    }
                } catch (err) {
                    console.error(err);
                }
            },

            async loadAttendanceHistory() {
                this.loadingHistory = true;
                this.historyMsg = '';
                let url = 'api.php?action=get_attendance_history';
                if (this.selectedSessionId) {
                    url += `&session_id=${this.selectedSessionId}`;
                } else if (this.historyFromDate && this.historyToDate) {
                    url += `&from_date=${this.historyFromDate}&to_date=${this.historyToDate}`;
                }
                try {
                    const res = await axios.get(url);
                    this.attendanceHistory = res.data;
                    this.historyTableHtml = res.data.map(h => `
                        <tr class="animate__animated animate__bounceIn">
                            <td class="px-6 py-3 text-white">
                                <input type="checkbox" ${h.status === 'Present' ? 'checked' : ''} @change="markManualAttendance(${h.student_id}, ${h.session_id}, $event.target.checked)" :disabled="!selectedSessionId" class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded">
                            </td>
                            <td class="px-6 py-3 text-white">${h.student_name}</td>
                            <td class="px-6 py-3 text-white">${h.unit_name}</td>
                            <td class="px-6 py-3 text-white">${h.status}</td>
                            <td class="px-6 py-3 text-white">${h.session_date}</td>
                        </tr>
                    `).join('');
                } catch (err) {
                    console.error(err);
                    this.historyMsg = 'Error loading attendance';
                    this.historySuccess = false;
                }
                this.loadingHistory = false;
            },

            async markManualAttendance(student_id, session_id, isChecked) {
                this.historyMsg = '';
                try {
                    const res = await axios.post('api.php', {
                        action: 'mark_manual_attendance',
                        student_id,
                        session_id,
                        status: isChecked ? 'Present' : 'Absent'
                    });
                    this.historyMsg = res.data.message;
                    this.historySuccess = res.data.success;
                    if (res.data.success) {
                        setTimeout(() => this.historyMsg = '', 3000);
                        await this.loadAttendanceHistory(); // Refresh table
                    }
                } catch (err) {
                    this.historyMsg = 'Error updating attendance';
                    this.historySuccess = false;
                }
            },

            exportAttendance() {
                let url = 'api.php?action=export_attendance';
                if (this.historyFromDate && this.historyToDate) {
                    url += `&from_date=${this.historyFromDate}&to_date=${this.historyToDate}`;
                }
                window.location.href = url;
            },

            async scheduleSession() {
                this.loadingSchedule = true;
                this.scheduleMsg = '';
                if (!this.schedule.assignment_id || !this.schedule.session_date || !this.schedule.start_time || !this.schedule.end_time || !this.schedule.venue) {
                    this.scheduleMsg = 'All fields are required';
                    this.scheduleSuccess = false;
                    this.loadingSchedule = false;
                    return;
                }
                try {
                    const res = await axios.post('api.php', { action: 'schedule_session', ...this.schedule });
                    this.scheduleMsg = res.data.message;
                    this.scheduleSuccess = res.data.success;
                    if (res.data.success) {
                        this.schedule = { assignment_id: '', session_date: '', start_time: '', end_time: '', venue: '' };
                        setTimeout(() => this.scheduleMsg = '', 3000);
                        await this.loadScheduledSessions();
                    }
                } catch (err) {
                    this.scheduleMsg = 'Error scheduling session';
                    this.scheduleSuccess = false;
                }
                this.loadingSchedule = false;
            },

            async loadStudents(unit_id) {
                try {
                    const res = await axios.get(`api.php?action=get_students&unit_id=${unit_id}`);
                    console.log('Loading students for unit:', unit_id, 'Response:', res.data);

                    this.labeledDescriptors = res.data.map(s => {
                        try {
                            if (!s.face_encoding || s.face_encoding.trim() === '') {
                                console.warn('Empty face encoding for student:', s.student_id);
                                return null;
                            }

                            const parsed = JSON.parse(s.face_encoding);
                            console.log('Parsed face encoding for', s.student_id, ':', parsed);

                            if (!Array.isArray(parsed) || parsed.length === 0) {
                                console.warn('Invalid face encoding format for student:', s.student_id);
                                return null;
                            }

                            // Ensure we have the right descriptor length (128 for face-api.js)
                            const descriptors = parsed.map(desc => {
                                if (Array.isArray(desc) && desc.length === 128) {
                                    return new Float32Array(desc);
                                } else if (Array.isArray(desc) && desc.length !== 128) {
                                    console.warn('Face descriptor wrong length:', desc.length, 'expected 128');
                                    return null;
                                }
                                return new Float32Array(desc);
                            }).filter(d => d !== null);

                            if (descriptors.length === 0) {
                                console.warn('No valid descriptors for student:', s.student_id);
                                return null;
                            }

                            const label = `${s.first_name} ${s.last_name}`.trim();
                            console.log('Creating labeled descriptor for:', label, 'with', descriptors.length, 'descriptors');
                            return new faceapi.LabeledFaceDescriptors(label, descriptors);

                        } catch (err) {
                            console.error('Error processing face encoding for student:', s.student_id, err);
                            return null;
                        }
                    }).filter(d => d);

                    console.log('Loaded', this.labeledDescriptors.length, 'labeled descriptors');
                } catch (err) {
                    console.error('Error loading students:', err);
                    this.labeledDescriptors = [];
                }
            },

            async selectSession(session) {
                this.selectedSession = session;
                this.startFaceRecognition(session.unit_id, session.session_id);
            },

            async startFaceRecognition(unit_id, session_id) {
                try {
                    // Parallel model loading for better performance
                    console.time('Model Loading');
                    await Promise.all([
                        faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                    ]);
                    console.timeEnd('Model Loading');

                    // Load student data in parallel with video setup
                    console.time('Student Loading');
                    const studentPromise = this.loadStudents(unit_id);

                    console.time('Video Setup');
                    const video = document.getElementById('video');
                    const streamPromise = navigator.mediaDevices.getUserMedia({
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                            frameRate: { ideal: 30 }
                        }
                    });

                    // Wait for both student data and video stream
                    const [stream] = await Promise.all([streamPromise, studentPromise]);
                    video.srcObject = stream;
                    this.videoStream = stream;
                    console.timeEnd('Video Setup');
                    console.timeEnd('Student Loading');
                    video.addEventListener('play', () => {
                        // Wait for video to have proper dimensions
                        const checkDimensions = () => {
                            if (video.videoWidth > 0 && video.videoHeight > 0) {
                                const canvas = faceapi.createCanvasFromMedia(video);
                                document.body.append(canvas);
                                const displaySize = { width: video.videoWidth, height: video.videoHeight };
                                faceapi.matchDimensions(canvas, displaySize);

                                const faceMatcher = new faceapi.FaceMatcher(this.labeledDescriptors, 0.2);
                                // Track recognized students to avoid duplicate API calls
                                const recognizedStudents = new Set();

                                this.recognitionInterval = setInterval(async () => {
                                    if (!this.isRecognizing) return;

                                    try {
                                        const detections = await faceapi.detectAllFaces(video).withFaceLandmarks().withFaceDescriptors();
                                        const resized = faceapi.resizeResults(detections, displaySize);
                                        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                                        faceapi.draw.drawDetections(canvas, resized);

                                        const detectedStudents = document.getElementById('detected-students');
                                        const currentTime = Date.now();

                                        // Clear old detections (older than 30 seconds)
                                        Array.from(detectedStudents.children).forEach(li => {
                                            const timestamp = parseInt(li.dataset.timestamp);
                                            if (currentTime - timestamp > 30000) {
                                                li.remove();
                                                const studentName = li.textContent.split(' - ')[0];
                                                recognizedStudents.delete(studentName);
                                            }
                                        });

                                        resized.forEach(d => {
                                            const bestMatch = faceMatcher.findBestMatch(d.descriptor);
                                            if (bestMatch.label !== 'unknown' && bestMatch.distance < 0.2) {
                                                const studentName = bestMatch.label;
                                                const confidence = 1 - bestMatch.distance;

                                                // Only mark attendance if confidence is 0.8 (80%) or higher
                                                if (confidence >= 0.8) {
                                                    // Only mark attendance if not already recognized recently
                                                    if (!recognizedStudents.has(studentName)) {
                                                        recognizedStudents.add(studentName);

                                                        // Add to UI with color coding based on confidence
                                                        const li = document.createElement('li');
                                                        li.textContent = `${studentName} - Present (Confidence: ${confidence.toFixed(2)})`;
                                                        li.dataset.timestamp = currentTime;

                                                        // Color coding based on confidence level
                                                        if (confidence >= 0.9) {
                                                            li.className = 'text-green-600 font-semibold'; // Excellent (90%+)
                                                        } else {
                                                            li.className = 'text-orange-600 font-medium'; // Good (80-89%)
                                                        }

                                                        detectedStudents.appendChild(li);

                                                        // Mark attendance via API
                                                        axios.post('api.php', {
                                                            action: 'mark_attendance',
                                                            student_name: studentName,
                                                            session_id: session_id,
                                                            confidence: confidence
                                                        }).then(response => {
                                                            console.log('Attendance marked for:', studentName, 'with confidence:', confidence.toFixed(2), response.data);
                                                        }).catch(error => {
                                                            console.error('Error marking attendance:', error);
                                                            // Remove from UI if API call failed
                                                            li.remove();
                                                            recognizedStudents.delete(studentName);
                                                        });
                                                    }
                                                } else {
                                                    // Low confidence - show in red but don't register
                                                    if (!recognizedStudents.has(studentName)) {
                                                        const li = document.createElement('li');
                                                        li.textContent = `${studentName} - Low Confidence (${confidence.toFixed(2)})`;
                                                        li.dataset.timestamp = currentTime;
                                                        li.className = 'text-red-600 font-medium'; // Poor (<80%)
                                                        detectedStudents.appendChild(li);

                                                        // Auto-remove after 5 seconds
                                                        setTimeout(() => {
                                                            if (li.parentNode) {
                                                                li.remove();
                                                            }
                                                        }, 5000);

                                                        console.log(`Low confidence for ${studentName}: ${confidence.toFixed(2)} (below 0.8 threshold - not registered)`);
                                                    }
                                                }
                                            }
                                        });

                                        // Update status
                                        this.faceError = `Detecting... Found ${resized.length} face(s)`;

                                    } catch (error) {
                                        console.error('Face recognition error:', error);
                                        this.faceError = 'Face recognition error: ' + error.message;
                                    }
                                }, 1000); // Check every 1 second for better responsiveness
                            } else {
                                // Video dimensions not ready, check again
                                setTimeout(checkDimensions, 100);
                            }
                        };

                        // Start checking dimensions
                        checkDimensions();
                    });
                } catch (err) {
                    this.faceError = 'Error starting face recognition: ' + (err.message || 'Unknown error');
                }
            },

            toggleRecognition() {
                this.isRecognizing = !this.isRecognizing;
            },

            stopRecognition() {
                if (this.recognitionInterval) clearInterval(this.recognitionInterval);
                if (this.videoStream) this.videoStream.getTracks().forEach(track => track.stop());
                const video = document.getElementById('video');
                video.srcObject = null;
                this.faceError = '';
            },

            init() {
                console.log('Alpine.js dashboard component initialized'); // Debug: Component init
                this.switchTab('dashboard');
                setInterval(() => this.loadNotifications(), 30000);

                // Expose switchTab function globally for navbar
                window.switchTab = (tabName) => this.switchTab(tabName);
            }
        }));
    });
    </script>
</body>
</html>
