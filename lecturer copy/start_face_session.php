<?php
session_start();
require_once '../db.php';
require_once 'lecturer_controller.php';
require_once 'config.php';

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../logout.php", true, 303);
    exit;
}
$_SESSION['last_activity'] = time();

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../access_denied.php", true, 303);
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Initialize controller
try {
    $controller = new LecturerController($conn);
} catch (Exception $e) {
    error_log(date('[Y-m-d H:i:s]') . " Failed to initialize LecturerController: {$e->getMessage()}\n", 3, '../logs/errors.log');
    http_response_code(500);
    die("System error. Please contact support.");
}

// Fetch theme color
$theme_color = '#6c757d';
if ($conn && !$conn->connect_error) {
    if ($stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'")) {
        if ($stmt->execute() && $stmt->bind_result($theme_color) && $stmt->fetch()) {
            // Theme color fetched
        }
        $stmt->close();
    } else {
        error_log(date('[Y-m-d H:i:s]') . " Failed to fetch theme color: {$conn->error}\n", 3, '../logs/errors.log');
    }
} else {
    error_log(date('[Y-m-d H:i:s]') . " Database connection failed: {$conn->connect_error}\n", 3, '../logs/errors.log');
}

// Fetch sessions (active within 24 hours)
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$result = $controller->getClassSessions($lecturer_id, null);
$sessions = $result['success'] ? array_filter($result['sessions'], function($session) use ($today, $tomorrow) {
    $session_datetime = strtotime("{$session['session_date']} {$session['end_time']}");
    $now = time();
    error_log(date('[Y-m-d H:i:s]') . " Filtering session: ID={$session['session_id']}, Date={$session['session_date']}, EndTime={$session['end_time']}, Datetime=$session_datetime, Now=$now\n", 3, '../logs/debug.log');
    return $session['session_date'] >= $today && $session['session_date'] <= $tomorrow && $session_datetime > $now;
}) : [];
if (empty($sessions)) {
    error_log(date('[Y-m-d H:i:s]') . " No active sessions for lecturer_id: $lecturer_id\n", 3, '../logs/errors.log');
}

// Precompute norm
function precomputeNorm($vector) {
    return sqrt(array_sum(array_map(fn($val) => $val * $val, $vector)));
}

// Decompress face descriptor
function decompressDescriptor($compressed) {
    $decompressed = [];
    $last = 0;
    foreach ($compressed as $delta) {
        $last += $delta / 10000;
        $decompressed[] = max(-1.5, min(1.5, $last));
    }
    error_log(date('[Y-m-d H:i:s]') . " Decompressed descriptor (first 10): " . json_encode(array_slice($decompressed, 0, 10)) . "\n", 3, '../logs/debug.log');
    return $decompressed;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_start(); // Start output buffering
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'process_face') {
        try {
            $session_id = (int)($_POST['session_id'] ?? 0);
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);
            $face_descriptor = json_decode($_POST['face_descriptor'] ?? '[]', true);

            error_log(date('[Y-m-d H:i:s]') . " Received descriptor (first 10): " . json_encode(array_slice($face_descriptor, 0, 10)) . "\n", 3, '../logs/debug.log');

            if (!$session_id || !$assignment_id || !is_array($face_descriptor) || count($face_descriptor) !== 128 ||
                !array_reduce($face_descriptor, fn($carry, $val) => $carry && is_numeric($val), true)) {
                throw new Exception("Invalid face descriptor data.");
            }
            $face_descriptor = decompressDescriptor($face_descriptor);

            if (!array_reduce($face_descriptor, fn($carry, $val) => $carry && is_numeric($val) && abs($val) <= 1.5, true)) {
                throw new Exception("Decompressed descriptor contains invalid values.");
            }

            $stmt = $conn->prepare("
                SELECT session_id, end_time, NOW() AS now_time
                FROM class_sessions
                WHERE session_id = ? AND lecturer_id = ?
            ");
            if (!$stmt) throw new Exception("Prepare failed: {$conn->error}");
            $stmt->bind_param("ii", $session_id, $lecturer_id);
            if (!$stmt->execute()) throw new Exception("Execute failed: {$stmt->error}");
            $result = $stmt->get_result();
            if ($result->num_rows === 0) throw new Exception("Invalid or unauthorized session.");
            $session_data = $result->fetch_assoc();
            $stmt->close();

            if (strtotime($session_data['now_time']) > strtotime($session_data['end_time'])) {
                throw new Exception("This session has ended.");
            }

            $stmt = $conn->prepare("
                SELECT s.student_id, s.first_name, s.last_name, u.face_encoding
                FROM student_enrollments se
                JOIN students s ON se.student_id = s.student_id
                JOIN users u ON s.student_id = u.user_id
                WHERE se.unit_id = (SELECT unit_id FROM lecturer_assignments WHERE assignment_id = ?)
                AND se.academic_year = (SELECT academic_year FROM lecturer_assignments WHERE assignment_id = ?)
                AND se.semester = (SELECT semester FROM lecturer_assignments WHERE assignment_id = ?)
                AND u.face_encoding IS NOT NULL AND u.face_encoding != '' AND u.face_encoding != '[]'
            ");
            if (!$stmt) throw new Exception("Prepare failed: {$conn->error}");
            $stmt->bind_param("iii", $assignment_id, $assignment_id, $assignment_id);
            if (!$stmt->execute()) throw new Exception("Execute failed: {$stmt->error}");
            $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($students)) {
                throw new Exception("No enrolled students with face encodings.");
            }

            foreach ($students as &$student) {
                $encodings = json_decode($student['face_encoding'], true);
                if (is_array($encodings)) {
                    $student['encodings'] = array_map(function($enc) {
                        return ['vector' => $enc, 'norm' => precomputeNorm($enc)];
                    }, $encodings);
                } else {
                    $student['encodings'] = [];
                }
            }
            unset($student);

            $matched_student = null;
            $base_threshold = 0.55;
            $fallback_threshold = 0.45;
            $best_similarity = -1;
            $best_student = null;

            foreach ($students as $student) {
                foreach ($student['encodings'] as $index => $enc) {
                    if (!is_array($enc['vector']) || count($enc['vector']) !== 128) {
                        error_log(date('[Y-m-d H:i:s]') . " Invalid encoding for student_id: {$student['student_id']}\n", 3, '../logs/errors.log');
                        continue;
                    }
                    $similarity = computeCosineSimilarity($face_descriptor, $enc['vector'], precomputeNorm($face_descriptor), $enc['norm']);
                    error_log(date('[Y-m-d H:i:s]') . " Comparing student_id: {$student['student_id']}, Encoding: $index, Similarity: $similarity\n", 3, '../logs/debug.log');
                    if ($similarity > $best_similarity) {
                        $best_similarity = $similarity;
                        $best_student = $student;
                    }
                    if ($similarity >= $base_threshold) {
                        $matched_student = $student;
                        break 2;
                    }
                }
            }

            if (!$matched_student && $best_similarity >= $fallback_threshold) {
                $matched_student = $best_student;
            }

            if (!$matched_student) throw new Exception("Student not recognized or not enrolled.");

            $student_id = $matched_student['student_id'];
            $stmt = $conn->prepare("
                SELECT attendance_id, status
                FROM attendance_records
                WHERE session_id = ? AND student_id = ?
            ");
            if (!$stmt) throw new Exception("Prepare failed: {$conn->error}");
            $stmt->bind_param("ii", $session_id, $student_id);
            if (!$stmt->execute()) throw new Exception("Execute failed: {$stmt->error}");
            $result = $stmt->get_result();
            $attendance = $result->fetch_assoc();
            $stmt->close();

            if ($attendance && $attendance['status'] === 'Present') {
                $student_name = trim($matched_student['first_name'] . ' ' . ($matched_student['last_name'] ?? ''));
                ob_clean();
                echo json_encode([
                    'status' => 'success',
                    'message' => "$student_name is already marked as Present.",
                    'student_id' => $student_id
                ]);
                exit;
            }

            $status = 'Present';
            if ($attendance) {
                $stmt = $conn->prepare("
                    UPDATE attendance_records
                    SET status = ?, marked_at = NOW()
                    WHERE session_id = ? AND student_id = ?
                ");
                $stmt->bind_param("sii", $status, $session_id, $student_id);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO attendance_records (session_id, student_id, status, marked_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->bind_param("iis", $session_id, $student_id, $status);
            }
            if (!$stmt->execute()) throw new Exception("Execute failed: {$stmt->error}");
            $stmt->close();

            $student_name = trim($matched_student['first_name'] . ' ' . ($matched_student['last_name'] ?? ''));
            ob_clean();
            echo json_encode([
                'status' => 'success',
                'message' => "$student_name marked as Present.",
                'student_id' => $student_id
            ]);
        } catch (Exception $e) {
            error_log(date('[Y-m-d H:i:s]') . " Error in process_face: {$e->getMessage()}\n", 3, '../logs/errors.log');
            ob_clean();
            echo json_encode([
                'status' => 'error',
                'message' => strpos($e->getMessage(), "Unknown column") !== false ? "Database configuration error." : $e->getMessage()
            ]);
        }
        ob_end_flush();
        exit;
    }
    ob_end_clean();
}

// Optimized cosine similarity
function computeCosineSimilarity($vec1, $vec2, $norm1, $norm2) {
    $dot = 0;
    for ($i = 0; $i < 128; $i++) {
        $dot += $vec1[$i] * $vec2[$i];
    }
    return ($norm1 * $norm2) ? $dot / ($norm1 * $norm2) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Recognition Session</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../js/human.js" onload="window.humanLoaded = true; console.log('Human loaded');" onerror="window.humanLoadError = true; console.error('Failed to load Human');"></script>
    <style>
        :root {
            --theme-color: <?php echo htmlspecialchars($theme_color); ?>;
        }
        .text-theme { color: var(--theme-color); }
        .bg-theme { background-color: var(--theme-color); }
        .hover:bg-theme:hover { background-color: var(--theme-color); }
        .focus:ring-theme:focus { --ring-color: var(--theme-color); }
        #video { display: none; max-width: 100%; }
        #canvas { position: absolute; top: 0; left: 0; }
        .feedback-box { max-height: 300px; overflow-y: auto; }
        .loading-spinner { border: 4px solid #f3f3f3; border-top: 4px solid var(--theme-color); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; display: inline-block; }
        #activity-log { width: 300px; height: 100px; resize: none; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-900 min-h-screen">
    <?php include 'lecturer_navbar.php'; ?>

    <div class="container mx-auto px-6 py-10">
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-theme">Face Recognition Session</h1>
        </header>

        <section id="session-selection" class="bg-white p-8 rounded">
            <?php if (empty($sessions)): ?>
                <p class="text-red-600 font-semibold">No active sessions available. Please schedule a session.</p>
            <?php else: ?>
                <form id="session-form" class="space-y-6">
                    <div>
                        <label for="session_id" class="block text-sm font-semibold text-gray-700 mb-2">Select Session</label>
                        <select id="session_id" name="session_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all" required>
                            <option value="">-- Select a session --</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['session_id']); ?>" 
                                        data-assignment-id="<?php echo htmlspecialchars($s['assignment_id']); ?>" 
                                        data-end-time="<?php echo htmlspecialchars($s['end_time']); ?>" 
                                        data-session-date="<?php echo htmlspecialchars($s['session_date']); ?>">
                                    <?php 
                                        $topic = !empty($s['session_topic']) ? $s['session_topic'] : 'No topic';
                                        echo htmlspecialchars("{$s['unit_name']} - {$s['session_date']} {$s['start_time']} - {$s['end_time']} at {$s['venue']} ($topic)");
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" id="start-session-btn" class="w-full bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Start Session
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <section id="camera-section" class="bg-white p-8 rounded-lg shadow-xl mb-8 hidden">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Live Camera Feed</h2>
            <div class="relative mb-6 flex justify-center">
                <video id="video" autoplay muted class="w-full max-w-lg rounded-lg shadow-md border border-gray-200"></video>
                <canvas id="canvas" class="absolute"></canvas>
            </div>
            <div class="flex space-x-4 mb-6 items-center">
                <button id="capture-btn" class="bg-blue-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:bg-gray-600 disabled:cursor-not-allowed" disabled>
                    Capture Face
                </button>
                <button id="end-session-btn" class="bg-red-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all">
                    End Session
                </button>
                <textarea id="activity-log" class="bg-gray-100 p-2 rounded-lg text-gray-700 border border-gray-200"></textarea>
            </div>
            <div id="status-messages" class="feedback-box bg-gray-100 p-4 rounded-lg text-gray-700 shadow-inner border border-gray-200"></div>
            <p id="session-timer" class="mt-4 text-sm text-gray-600"></p>
        </section>
    </div>

    <script>
        const humanConfig = {
            modelBasePath: 'https://vladmandic.github.io/human/models/',
            face: { enabled: true, detector: { rotation: true }, mesh: { enabled: true }, description: { enabled: true } },
            backend: 'webgl',
            debug: true
        };

        const App = {
            state: {
                sessionActive: false,
                isCapturing: false,
                modelsLoaded: false,
                stream: null,
                detectionInterval: null,
                sessionTimer: null,
                sessionEndTime: null,
                taskQueue: [],
                isProcessing: false,
                human: null
            },
            modules: {}
        };

        // Configuration Module
        App.modules.ConfigModule = {
            getBasePath() {
                const path = window.location.pathname.split('/').slice(0, -2).join('/') || '/att';
                console.log(`Resolved base path: ${path}`);
                return path;
            },
            modelPath: null,
            init() {
                const basePath = this.getBasePath();
                this.modelPath = `${basePath}/models`;
            },
            async checkEnvironment() {
                const errors = [];
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) errors.push('WebGL not supported');
                const hasCamera = await navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
                    stream.getTracks().forEach(track => track.stop());
                    return true;
                }).catch(() => false);
                if (!hasCamera) errors.push('No camera detected');
                console.log(`Environment: Browser=${navigator.userAgent}, WebGL=${!!gl}, Camera=${hasCamera}, ModelPath=${this.modelPath}`);
                return errors;
            }
        };

        // UI Module
        App.modules.UIModule = {
            elements: {
                sessionForm: null,
                sessionSelect: null,
                startSessionBtn: null,
                captureBtn: null,
                endSessionBtn: null,
                cameraSection: null,
                sessionSelection: null,
                timerDisplay: null,
                statusMessages: null,
                activityLog: null,
                video: null,
                canvas: null
            },
            init() {
                this.elements.sessionForm = document.getElementById('session-form');
                this.elements.sessionSelect = document.getElementById('session_id');
                this.elements.startSessionBtn = document.getElementById('start-session-btn');
                this.elements.captureBtn = document.getElementById('capture-btn');
                this.elements.endSessionBtn = document.getElementById('end-session-btn');
                this.elements.cameraSection = document.getElementById('camera-section');
                this.elements.sessionSelection = document.getElementById('session-selection');
                this.elements.timerDisplay = document.getElementById('session-timer');
                this.elements.statusMessages = document.getElementById('status-messages');
                this.elements.activityLog = document.getElementById('activity-log');
                this.elements.video = document.getElementById('video');
                this.elements.canvas = document.getElementById('canvas');
            },
            appendStatusMessage(message, className = 'text-gray-700') {
                if (message.includes('Detected 0 face(s)')) {
                    console.log(`Status: ${message}`);
                    return;
                }
                console.log(`Status: ${message}`);
                const p = document.createElement('p');
                p.className = `text-sm ${className}`;
                p.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
                this.elements.statusMessages.appendChild(p);
                this.elements.statusMessages.scrollTop = this.elements.statusMessages.scrollHeight;
            },
            logActivity(message) {
                const log = this.elements.activityLog;
                const lines = log.value.split('\n');
                lines.push(`[${new Date().toLocaleTimeString()}] ${message}`);
                if (lines.length > 100) lines.shift();
                log.value = lines.join('\n');
                log.scrollTop = log.scrollHeight;
                console.log(`Activity: ${message}`);
            },
            updateTimer(minutes, seconds) {
                this.elements.timerDisplay.textContent = `Session ends in ${minutes}m ${seconds}s`;
            },
            clearTimer() {
                this.elements.timerDisplay.textContent = '';
            }
        };

        // Camera Module
        App.modules.CameraModule = {
            context: null,
            async start() {
                App.modules.UIModule.logActivity('Starting camera');
                try {
                    App.state.stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
                    App.modules.UIModule.elements.video.srcObject = App.state.stream;
                    App.modules.UIModule.elements.video.style.display = 'block';
                    App.modules.UIModule.elements.canvas.width = App.modules.UIModule.elements.video.videoWidth || 640;
                    App.modules.UIModule.elements.canvas.height = App.modules.UIModule.elements.video.videoHeight || 480;
                    this.context = App.modules.UIModule.elements.canvas.getContext('2d');
                    await new Promise(resolve => App.modules.UIModule.elements.video.onloadedmetadata = resolve);
                    if (App.modules.UIModule.elements.video.readyState !== 4) throw new Error('Video stream not ready');
                    App.modules.UIModule.logActivity('Camera started');
                } catch (err) {
                    App.modules.UIModule.appendStatusMessage(`Camera error: ${err.message}`, 'text-red-600');
                    throw err;
                }
            },
            async stop() {
                App.modules.UIModule.logActivity('Stopping camera');
                if (App.state.stream) {
                    App.state.stream.getTracks().forEach(track => track.stop());
                    App.state.stream = null;
                    App.modules.UIModule.elements.video.srcObject = null;
                    App.modules.UIModule.elements.video.style.display = 'none';
                    this.context.clearRect(0, 0, App.modules.UIModule.elements.canvas.width, App.modules.UIModule.elements.canvas.height);
                    App.modules.UIModule.elements.canvas.style.display = 'none';
                    App.modules.UIModule.logActivity('Camera stopped');
                }
            }
        };

        // Detection Module
        App.modules.DetectionModule = {
            async loadModels(attempt = 1, maxAttempts = 3) {
                if (App.state.modelsLoaded) return;
                App.modules.UIModule.logActivity('Loading Human models');
                try {
                    App.state.human = new Human.Human(humanConfig);
                    await App.state.human.load();
                    App.state.modelsLoaded = true;
                    App.modules.UIModule.appendStatusMessage('Human models loaded successfully.', 'text-green-600');
                    App.modules.UIModule.logActivity('Models loaded');
                } catch (err) {
                    console.error(`Model loading attempt ${attempt} failed:`, err);
                    if (attempt < maxAttempts) {
                        App.modules.UIModule.appendStatusMessage(`Failed to load models. Retrying (${attempt + 1}/${maxAttempts})...`, 'text-yellow-600');
                        App.modules.UIModule.logActivity(`Retrying model load (${attempt + 1}/${maxAttempts})`);
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        return this.loadModels(attempt + 1, maxAttempts);
                    }
                    App.modules.UIModule.appendStatusMessage(`Failed to load models: ${err.message}`, 'text-red-600');
                    throw err;
                }
            },
            drawFaceRectangles(faces) {
                App.modules.CameraModule.context.clearRect(0, 0, App.modules.UIModule.elements.canvas.width, App.modules.UIModule.elements.canvas.height);
                faces.forEach(face => {
                    const { box } = face;
                    if (!box) return;
                    const [x, y, width, height] = box;
                    App.modules.CameraModule.context.strokeStyle = 'green';
                    App.modules.CameraModule.context.lineWidth = 2;
                    App.modules.CameraModule.context.strokeRect(x, y, width, height);
                    App.modules.CameraModule.context.fillStyle = 'green';
                    App.modules.CameraModule.context.font = '16px Arial';
                    App.modules.CameraModule.context.fillText(`Confidence: ${(face.score * 100).toFixed(2)}%`, x, y - 10);
                });
            },
            compressDescriptor(descriptor) {
                const compressed = [];
                let last = 0;
                for (const val of descriptor) {
                    const clamped = Math.max(-1, Math.min(1, val));
                    const delta = Math.round((clamped - last) * 10000);
                    compressed.push(delta);
                    last = clamped;
                }
                console.log('Compressed descriptor (first 10):', compressed.slice(0, 10));
                return compressed;
            },
            async detectFacesRealtime() {
                if (!App.state.sessionActive || !App.state.modelsLoaded) return;
                try {
                    const result = await App.state.human.detect(App.modules.UIModule.elements.video);
                    const faces = result.face || [];
                    this.drawFaceRectangles(faces);
                    App.modules.UIModule.appendStatusMessage(`Detected ${faces.length} face(s) in frame.`, 'text-gray-500');
                } catch (err) {
                    console.error('Real-time detection error:', err);
                    App.modules.UIModule.appendStatusMessage('Error detecting faces. Ensure face is well-lit.', 'text-red-600');
                }
            },
            async captureFace() {
                App.modules.UIModule.logActivity('Capturing face');
                let face = null;
                let retries = 0;
                const maxRetries = 3;

                while (!face && retries < maxRetries) {
                    try {
                        console.log(`Detection attempt ${retries + 1}`);
                        App.modules.UIModule.logActivity(`Detection attempt ${retries + 1}`);
                        if (App.modules.UIModule.elements.video.readyState !== 4) throw new Error('Video stream not ready');

                        const result = await App.state.human.detect(App.modules.UIModule.elements.video);
                        const faces = result.face || [];

                        if (faces.length === 1 && faces[0].descriptor && faces[0].score >= 0.7) {
                            face = faces[0];
                            console.log(`Face detected with confidence: ${face.score}`);
                            App.modules.UIModule.logActivity(`Detected face with confidence ${face.score.toFixed(2)}`);
                        } else {
                            console.log(`Retry ${retries + 1}: No face or low confidence (score: ${faces[0]?.score || 'none'})`);
                            retries++;
                            App.modules.UIModule.appendStatusMessage(`Retry ${retries}: No face detected. Ensure face is well-lit and centered.`, 'text-yellow-600');
                            App.modules.UIModule.logActivity(`Retry ${retries}: No face detected`);
                            await new Promise(resolve => setTimeout(resolve, 300));
                        }
                    } catch (err) {
                        console.error('Detection error:', err);
                        retries++;
                        App.modules.UIModule.appendStatusMessage(`Retry ${retries}: Error detecting face: ${err.message}`, 'text-yellow-600');
                        App.modules.UIModule.logActivity(`Detection error: ${err.message}`);
                        await new Promise(resolve => setTimeout(resolve, 300));
                    }
                }

                return face;
            }
        };

        // Session Module
        App.modules.SessionModule = {
            async startSession(sessionId, assignmentId, endDateTime) {
                App.state.sessionId = sessionId;
                App.state.assignmentId = assignmentId;
                App.state.sessionEndTime = new Date(endDateTime);
                App.state.sessionActive = true;
                await App.modules.CameraModule.start();
                App.modules.UIModule.elements.sessionSelection.classList.add('hidden');
                App.modules.UIModule.elements.cameraSection.classList.remove('hidden');
                App.modules.UIModule.elements.captureBtn.disabled = false;
                App.modules.UIModule.elements.endSessionBtn.disabled = false;
                App.modules.UIModule.appendStatusMessage('Session started. Ready to capture faces.', 'text-green-600');
                App.modules.UIModule.logActivity('Session started');
                this.startSessionTimer();
                App.state.detectionInterval = setInterval(() => App.modules.DetectionModule.detectFacesRealtime(), 300);
            },
            async endSession() {
                App.state.sessionActive = false;
                clearInterval(App.state.sessionTimer);
                clearInterval(App.state.detectionInterval);
                await App.modules.CameraModule.stop();
                App.modules.UIModule.elements.sessionSelection.classList.remove('hidden');
                App.modules.UIModule.elements.cameraSection.classList.add('hidden');
                App.modules.UIModule.elements.captureBtn.disabled = true;
                App.modules.UIModule.elements.endSessionBtn.disabled = true;
                App.modules.UIModule.appendStatusMessage('Session ended.', 'text-gray-500');
                App.modules.UIModule.logActivity('Session ended');
                App.modules.UIModule.clearTimer();
            },
            startSessionTimer() {
                App.state.sessionTimer = setInterval(() => {
                    const now = new Date();
                    if (now >= App.state.sessionEndTime || !App.state.sessionActive) {
                        this.endSession();
                    } else {
                        const timeLeft = Math.max(0, Math.floor((App.state.sessionEndTime - now) / 1000));
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        App.modules.UIModule.updateTimer(minutes, seconds);
                    }
                }, 1000);
            },
            async processFace(face) {
                App.modules.UIModule.logActivity('Sending face data to server');
                try {
                    const faceDescriptor = Array.from(face.descriptor);
                    console.log('Raw descriptor (first 10):', faceDescriptor.slice(0, 10));
                    if (faceDescriptor.length !== 128 || !faceDescriptor.every(val => typeof val === 'number' && isFinite(val))) {
                        throw new Error('Invalid face descriptor.');
                    }
                    const compressedDescriptor = App.modules.DetectionModule.compressDescriptor(faceDescriptor);

                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 5000);
                    const response = await fetch('start_face_session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'process_face',
                            session_id: App.state.sessionId,
                            assignment_id: App.state.assignmentId,
                            face_descriptor: JSON.stringify(compressedDescriptor)
                        }),
                        signal: controller.signal
                    });
                    clearTimeout(timeoutId);

                    if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                    const rawResponse = await response.text();
                    console.log('Raw server response:', rawResponse);
                    const result = JSON.parse(rawResponse);
                    App.modules.UIModule.logActivity('Received server response');

                    if (result.status === 'success') {
                        alert(result.message);
                        App.modules.UIModule.appendStatusMessage(result.message, 'text-green-600');
                    } else {
                        alert(result.message);
                        App.modules.UIModule.appendStatusMessage(`${result.message} Please retry`, 'text-red-600');
                    }
                } catch (err) {
                    console.error('Face processing error:', err);
                    App.modules.UIModule.appendStatusMessage(`Error processing face: ${err.message}`, 'text-red-600');
                    App.modules.UIModule.logActivity(`Error: ${err.message}`);
                    alert(`Error processing face: ${err.message}`);
                }
            }
        };

        // Task Queue for Synchronization
        App.modules.TaskQueue = {
            async enqueue(task) {
                App.state.taskQueue.push(task);
                if (!App.state.isProcessing) {
                    await this.processQueue();
                }
            },
            async processQueue() {
                App.state.isProcessing = true;
                while (App.state.taskQueue.length > 0) {
                    const task = App.state.taskQueue.shift();
                    try {
                        await task();
                    } catch (err) {
                        console.error('Task error:', err);
                        App.modules.UIModule.appendStatusMessage(`Task error: ${err.message}`, 'text-red-600');
                    }
                }
                App.state.isProcessing = false;
            }
        };

        // Main App Initialization
        document.addEventListener('DOMContentLoaded', async () => {
            App.modules.UIModule.init();
            App.modules.ConfigModule.init();

            const envErrors = await App.modules.ConfigModule.checkEnvironment();
            if (envErrors.length > 0) {
                envErrors.forEach(err => App.modules.UIModule.appendStatusMessage(err, 'text-red-600'));
                return;
            }

            if (window.humanLoadError) {
                App.modules.UIModule.appendStatusMessage('Failed to load Human library.', 'text-red-600');
                return;
            }

            if (typeof Human === 'undefined') {
                App.modules.UIModule.appendStatusMessage('Failed to initialize Human library.', 'text-red-600');
                return;
            }

            await App.modules.TaskQueue.enqueue(async () => {
                try {
                    await App.modules.DetectionModule.loadModels();
                    App.modules.UIModule.appendStatusMessage('System ready.', 'text-green-600');
                } catch (err) {
                    App.modules.UIModule.appendStatusMessage(`Initialization failed: ${err.message}`, 'text-red-600');
                    console.error('Initialization error:', err);
                }
            });

            // Session form submission
            App.modules.UIModule.elements.sessionForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (App.state.sessionActive) return;
                const sessionId = App.modules.UIModule.elements.sessionSelect.value;
                const selectedOption = App.modules.UIModule.elements.sessionSelect.selectedOptions[0];
                const assignmentId = selectedOption.dataset.assignmentId;
                const sessionDate = selectedOption.dataset.sessionDate;
                const endTime = selectedOption.dataset.endTime;
                if (!sessionId || !assignmentId || !sessionDate || !endTime) {
                    App.modules.UIModule.appendStatusMessage('Please select a valid session.', 'text-red-600');
                    return;
                }
                const endDateTime = new Date(`${sessionDate}T${endTime}`);
                await App.modules.TaskQueue.enqueue(() => App.modules.SessionModule.startSession(sessionId, assignmentId, endDateTime));
            });

            // Capture face button
            App.modules.UIModule.elements.captureBtn?.addEventListener('click', async () => {
                if (App.state.isCapturing || !App.state.sessionActive) return;
                App.state.isCapturing = true;
                App.modules.UIModule.elements.captureBtn.disabled = true;
                App.modules.UIModule.appendStatusMessage('Capturing face, please hold still...', 'text-blue-600');
                await App.modules.TaskQueue.enqueue(async () => {
                    try {
                        const face = await App.modules.DetectionModule.captureFace();
                        if (face) {
                            await App.modules.SessionModule.processFace(face);
                        } else {
                            App.modules.UIModule.appendStatusMessage('Failed to capture face after retries.', 'text-red-600');
                        }
                    } finally {
                        App.state.isCapturing = false;
                        App.modules.UIModule.elements.captureBtn.disabled = false;
                    }
                });
            });

            // End session button
            App.modules.UIModule.elements.endSessionBtn?.addEventListener('click', async () => {
                if (!App.state.sessionActive) return;
                await App.modules.TaskQueue.enqueue(() => App.modules.SessionModule.endSession());
            });
        });
    </script>
</body>
</html>