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
<body class="bg-gray-100 text-gray-800" x-data="dashboard">
    <?php include 'navbar.php'; ?>

    <main class="container mx-auto flex justify-center pt-32">
        <div class="w-full max-w-4xl">
            <!-- Dashboard Cards -->
            <div x-show="tab === 'dashboard'" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 animate__animated animate__fadeIn">
                <div class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-all">
                    <h2 class="text-lg font-semibold mb-2">Pending Notifications</h2>
                    <p class="text-3xl font-bold" x-text="notificationCount"></p>
                </div>
                <div class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-all">
                    <h2 class="text-lg font-semibold mb-2">Upcoming Sessions</h2>
                    <p class="text-3xl font-bold" x-text="Object.values(scheduledSessions).flat().length"></p>
                </div>
                <div class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-all">
                    <h2 class="text-lg font-semibold mb-2">My Units</h2>
                    <p class="text-3xl font-bold" x-text="myUnits.length"></p>
                </div>
                <div class="bg-white shadow rounded-lg p-6 hover:shadow-lg transition-all">
                    <h2 class="text-lg font-semibold mb-2">Attendance Today</h2>
                    <p class="text-3xl font-bold" x-text="attendanceHistory.filter(h => h.session_date === new Date().toISOString().split('T')[0]).length"></p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white shadow rounded-lg p-6 animate__animated animate__fadeIn">
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
                        <h3 class="font-semibold mb-2">Select a Scheduled Session</h3>
                        <template x-for="(sessions, unit_name) in scheduledSessions">
                            <div class="mb-4">
                                <h4 class="text-lg font-medium" x-text="unit_name"></h4>
                                <ul class="space-y-2">
                                    <template x-for="session in sessions">
                                        <li class="p-2 border rounded cursor-pointer hover:bg-gray-100 animate__animated animate__pulse" @click="selectSession(session)">
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
                            <h3 class="font-semibold mb-2 text-center">Detected Students</h3>
                            <ul id="detected-students" class="list-disc pl-6 text-gray-700 space-y-2"></ul>
                        </div>
                    </div>
                </div>

                <!-- Schedule Session Tab -->
                <div x-show="tab === 'schedule-session'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Schedule New Session</h2>
                    <form @submit.prevent="scheduleSession" class="space-y-4">
                        <select x-model="schedule.assignment_id" required class="w-full p-2 border rounded focus:ring-theme">
                            <option value="">Select Unit</option>
                            <?php foreach ($assignments as $a): ?>
                                <option value="<?= $a['assignment_id'] ?>"><?= htmlspecialchars($a['unit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" x-model="schedule.session_date" required class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="time" x-model="schedule.start_time" required class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="time" x-model="schedule.end_time" required class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="text" x-model="schedule.venue" placeholder="Venue" required class="w-full p-2 border rounded focus:ring-theme" />
                        <button type="submit" class="w-full px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse" :disabled="loadingSchedule">Schedule</button>
                    </form>
                    <p x-text="scheduleMsg" class="mt-2 text-center" :class="{'text-green-600': scheduleSuccess, 'text-red-600': !scheduleSuccess}"></p>
                </div>

                <!-- Attendance History Tab (with manual marking) -->
                <div x-show="tab === 'attendance-history'" class="transition-all animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">Attendance History</h2>
                    <div class="mb-4 flex flex-col md:flex-row md:space-x-4 space-y-2 md:space-y-0 items-center">
                        <select x-model="selectedSessionId" @change="loadAttendanceHistory" class="w-full md:w-1/2 p-2 border rounded focus:ring-theme">
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
                            <input type="date" x-model="historyFromDate" class="p-2 border rounded">
                            <input type="date" x-model="historyToDate" class="p-2 border rounded">
                            <button @click="loadAttendanceHistory" class="px-4 py-2 bg-theme text-white rounded hover:bg-opacity-90 animate__animated animate__pulse">Filter</button>
                            <button @click="exportAttendance" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 animate__animated animate__pulse">Export CSV</button>
                        </div>
                    </div>
                    <div x-show="loadingHistory" class="text-center py-4">Loading...</div>
                    <table x-show="!loadingHistory" class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left">Mark</th>
                                <th class="px-6 py-3 text-left">Student</th>
                                <th class="px-6 py-3 text-left">Unit</th>
                                <th class="px-6 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200" x-html="historyTableHtml"></tbody>
                    </table>
                    <p x-text="historyMsg" class="mt-2 text-center" :class="{'text-green-600': historySuccess, 'text-red-600': !historySuccess}"></p>
                </div>

                <!-- My Units Tab -->
                <div x-show="tab === 'my-units'" class="transition-all max-w-lg mx-auto animate__animated animate__fadeIn">
                    <h2 class="text-xl font-semibold mb-4 text-center">My Course Units</h2>
                    <div x-show="loadingMyUnits" class="text-center py-4">Loading...</div>
                    <div x-show="!loadingMyUnits" class="space-y-4">
                        <template x-for="unit in myUnits">
                            <div class="p-4 border rounded animate__animated animate__bounceIn">
                                <h3 class="font-semibold mb-2 cursor-pointer" @click="unit.expanded = !unit.expanded" x-text="unit.unit_name"></h3>
                                <div x-show="unit.expanded">
                                    <p class="text-gray-600">Enrolled Students:</p>
                                    <p x-text="unit.students || 'No students enrolled'"></p>
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
                        <input type="email" x-model="profile.email" placeholder="Email" required class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="text" x-model="profile.phone_number" placeholder="Phone Number" required class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="password" x-model="profile.password" placeholder="New Password (leave blank to keep current)" class="w-full p-2 border rounded focus:ring-theme" />
                        <input type="password" x-model="profile.password_confirm" placeholder="Confirm New Password" class="w-full p-2 border rounded focus:ring-theme" />
                        <p class="text-gray-600">Username: <span x-text="profile.username"></span></p>
                        <p class="text-gray-600">Staff Number: <span x-text="profile.staff_number"></span></p>
                        <p class="text-gray-600">Rank: <span x-text="profile.rank"></span></p>
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
                            <li class="p-4 border rounded bg-gray-50 hover:bg-gray-100 transition-all cursor-pointer animate__animated animate__bounceIn" @click="markRead(notif.notification_id)" :class="{'opacity-50': notif.is_read}">
                                <p x-text="notif.message"></p>
                                <p class="text-xs text-gray-400" x-text="notif.created_at"></p>
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
                this.tab = newTab;
                if (newTab === 'face-recognition' || newTab === 'attendance-history') await this.loadScheduledSessions();
                if (newTab === 'attendance-history') await this.loadAttendanceHistory();
                if (newTab === 'notifications') await this.loadNotifications();
                if (newTab === 'my-units') await this.loadMyUnits();
                if (newTab === 'profile') await this.loadProfile();
            },

            async loadScheduledSessions() {
                this.loadingSessions = true;
                try {
                    const res = await axios.get('api.php?action=get_scheduled_sessions');
                    this.scheduledSessions = res.data;
                } catch (err) {
                    console.error(err);
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
                            <td class="px-6 py-3">
                                <input type="checkbox" ${h.status === 'Present' ? 'checked' : ''} @change="markManualAttendance(${h.student_id}, ${h.session_id}, $event.target.checked)" :disabled="!selectedSessionId">
                            </td>
                            <td class="px-6 py-3">${h.student_name}</td>
                            <td class="px-6 py-3">${h.unit_name}</td>
                            <td class="px-6 py-3">${h.status}</td>
                            <td class="px-6 py-3">${h.session_date}</td>
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
                const res = await axios.get(`api.php?action=get_students&unit_id=${unit_id}`);
                this.labeledDescriptors = res.data.map(s => {
                    try {
                        const descriptors = [new Float32Array(JSON.parse(s.face_encoding))];
                        return new faceapi.LabeledFaceDescriptors(`${s.first_name} ${s.last_name}`, descriptors);
                    } catch (err) {
                        console.error('Invalid face encoding for student:', s.student_id);
                        return null;
                    }
                }).filter(d => d);
            },

            async selectSession(session) {
                this.selectedSession = session;
                this.startFaceRecognition(session.unit_id, session.session_id);
            },

            async startFaceRecognition(unit_id, session_id) {
                try {
                    await Promise.all([
                        faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                    ]);
                    await this.loadStudents(unit_id);
                    const video = document.getElementById('video');
                    this.videoStream = await navigator.mediaDevices.getUserMedia({ video: {} });
                    video.srcObject = this.videoStream;
                    video.addEventListener('play', () => {
                        const canvas = faceapi.createCanvasFromMedia(video);
                        document.body.append(canvas);
                        const displaySize = { width: video.width, height: video.height };
                        faceapi.matchDimensions(canvas, displaySize);
                        const faceMatcher = new faceapi.FaceMatcher(this.labeledDescriptors, 0.6);
                        this.recognitionInterval = setInterval(async () => {
                            if (!this.isRecognizing) return;
                            const detections = await faceapi.detectAllFaces(video).withFaceLandmarks().withFaceDescriptors();
                            const resized = faceapi.resizeResults(detections, displaySize);
                            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                            faceapi.draw.drawDetections(canvas, resized);
                            const detectedStudents = document.getElementById('detected-students');
                            detectedStudents.innerHTML = '';
                            resized.forEach(d => {
                                const bestMatch = faceMatcher.findBestMatch(d.descriptor);
                                if (bestMatch.label !== 'unknown') {
                                    const li = document.createElement('li');
                                    li.textContent = `${bestMatch.label} - Present`;
                                    detectedStudents.appendChild(li);
                                    axios.post('api.php', { action: 'mark_attendance', student_name: bestMatch.label, session_id });
                                }
                            });
                        }, 2000);
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
                this.switchTab('dashboard');
                setInterval(() => this.loadNotifications(), 30000);
            }
        }));
    });
    </script>
</body>
</html>