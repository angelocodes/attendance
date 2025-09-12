<?php
session_start();
require_once '../db.php';
require_once 'lecturer_controller.php';
require_once 'config.php';

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../logout.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../access_denied.php");
    exit();
}
$lecturer_id = (int)$_SESSION['user_id'];

try {
    $controller = new LecturerController($conn);
} catch (Exception $e) {
    error_log("Failed to initialize LecturerController: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
    die("System error. Please try again later.");
}

// Fetch theme color
$theme_color = '#6c757d';
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($theme_color);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log("Failed to fetch theme color: " . ($stmt ? $stmt->error : 'Statement preparation failed') . "\n", 3, '../logs/errors.log');
    }
} else {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error') . "\n", 3, '../logs/errors.log');
}

// Fetch sessions (active within the next 24 hours)
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$result = $controller->getClassSessions($lecturer_id, null);
$sessions = $result['success'] ? array_filter($result['sessions'], function($session) use ($today, $tomorrow) {
    $session_datetime = strtotime($session['session_date'] . ' ' . $session['end_time']);
    $now = time();
    error_log("Filtering session: ID={$session['session_id']}, Date={$session['session_date']}, EndTime={$session['end_time']}, Datetime=$session_datetime, Now=$now\n", 3, '../logs/debug.log');
    return $session['session_date'] >= $today && $session['session_date'] <= $tomorrow && $session_datetime > $now;
}) : [];
if (empty($sessions)) {
    error_log("No active sessions found for lecturer_id: $lecturer_id\n", 3, '../logs/errors.log');
}

// Precompute norms for stored encodings
function precomputeNorm($vector) {
    $norm = 0;
    foreach ($vector as $val) {
        $norm += $val * $val;
    }
    return sqrt($norm);
}

// Handle AJAX face recognition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_face') {
    ob_clean(); // Clear any stray output
    header('Content-Type: application/json');
    try {
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $assignment_id = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
        $face_descriptor = isset($_POST['face_descriptor']) ? json_decode($_POST['face_descriptor'], true) : [];

        if (!$session_id || !$assignment_id || !is_array($face_descriptor) || count($face_descriptor) !== 128 || !array_reduce($face_descriptor, function($carry, $val) { return $carry && is_numeric($val); }, true)) {
            throw new Exception("Invalid face descriptor data.");
        }

        // Verify session
        $query = "
            SELECT cs.session_id, cs.end_time, NOW() AS now_time
            FROM class_sessions cs
            WHERE cs.session_id = ? AND cs.lecturer_id = ?
        ";
        error_log("Executing query: $query with session_id=$session_id, lecturer_id=$lecturer_id\n", 3, '../logs/debug.log');
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Error preparing session verification: " . $conn->error);
        }
        $stmt->bind_param("ii", $session_id, $lecturer_id);
        if (!$stmt->execute()) {
            throw new Exception("Error verifying session: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Invalid or unauthorized session.");
        }
        $session_data = $result->fetch_assoc();
        $stmt->close();

        // Check if session has ended
        if (strtotime($session_data['now_time']) > strtotime($session_data['end_time'])) {
            error_log("Session expired: session_id=$session_id, now={$session_data['now_time']}, end_time={$session_data['end_time']}\n", 3, '../logs/errors.log');
            throw new Exception("This session has ended.");
        }

        // Fetch enrolled students
        $stmt = $conn->prepare("
            SELECT s.student_id, s.first_name, s.last_name, u.face_encoding
            FROM student_enrollments se
            JOIN students s ON se.student_id = s.student_id
            JOIN users u ON s.student_id = u.user_id
            WHERE se.unit_id = (SELECT unit_id FROM lecturer_assignments WHERE assignment_id = ?)
            AND se.academic_year = (SELECT academic_year FROM lecturer_assignments WHERE assignment_id = ?)
            AND se.semester = (SELECT semester FROM lecturer_assignments WHERE assignment_id = ?)
            AND u.face_encoding IS NOT NULL
            AND u.face_encoding != ''
            AND u.face_encoding != '[]'
        ");
        if (!$stmt) {
            throw new Exception("Error preparing student query: " . $conn->error);
        }
        $stmt->bind_param("iii", $assignment_id, $assignment_id, $assignment_id);
        if (!$stmt->execute()) {
            throw new Exception("Error fetching students: " . $stmt->error);
        }
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($students)) {
            error_log("No enrolled students with valid face encodings for assignment_id: $assignment_id\n", 3, '../logs/errors.log');
            throw new Exception("No enrolled students with face encodings found.");
        }

        // Compare face descriptors
        $matched_student = null;
        $base_threshold = 0.5;
        $fallback_threshold = 0.4;
        $best_similarity = -1;
        $best_student = null;

        foreach ($students as $student) {
            if (empty($student['face_encoding'])) {
                error_log("Empty face encoding for student_id: {$student['student_id']}\n", 3, '../logs/errors.log');
                continue;
            }
            $stored_encodings = json_decode($student['face_encoding'], true);
            if (!is_array($stored_encodings) || empty($stored_encodings)) {
                error_log("Invalid face encoding format for student_id: {$student['student_id']}\n", 3, '../logs/errors.log');
                continue;
            }

            foreach ($stored_encodings as $index => $stored_descriptor) {
                if (!is_array($stored_descriptor) || count($stored_descriptor) !== 128 || !array_reduce($stored_descriptor, function($carry, $val) { return $carry && is_numeric($val); }, true)) {
                    error_log("Invalid face encoding at index $index for student_id: {$student['student_id']}\n", 3, '../logs/errors.log');
                    continue;
                }
                $similarity = computeCosineSimilarity($face_descriptor, $stored_descriptor);
                error_log("Comparing student_id: {$student['student_id']}, Encoding: $index, Similarity: $similarity\n", 3, '../logs/debug.log');
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

        // Fallback to lower threshold if no match
        if (!$matched_student && $best_similarity >= $fallback_threshold) {
            $matched_student = $best_student;
            error_log("Fallback match for student_id: {$matched_student['student_id']}, Similarity: $best_similarity\n", 3, '../logs/debug.log');
        }

        if (!$matched_student) {
            throw new Exception("Student not recognized or not enrolled in this course unit.");
        }

        // Mark attendance
        $student_id = $matched_student['student_id'];
        $status = 'Present';
        $stmt = $conn->prepare("
            SELECT attendance_id
            FROM attendance_records
            WHERE session_id = ? AND student_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Error preparing attendance check: " . $conn->error);
        }
        $stmt->bind_param("ii", $session_id, $student_id);
        if (!$stmt->execute()) {
            throw new Exception("Error checking attendance: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        if ($exists) {
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
        if (!$stmt->execute()) {
            throw new Exception("Error saving attendance: " . $stmt->error);
        }
        $stmt->close();

        $student_name = trim($matched_student['first_name'] . ' ' . ($matched_student['last_name'] ?? ''));
        echo json_encode([
            'status' => 'success',
            'message' => "$student_name marked as Present."
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Error in process_face: " . $e->getMessage() . "\n", 3, '../logs/errors.log');
        $user_message = strpos($e->getMessage(), "Unknown column") !== false ?
            "System error: Database configuration issue." :
            $e->getMessage();
        echo json_encode(['status' => 'error', 'message' => $user_message]);
        exit;
    }
}

// Cosine similarity function
function computeCosineSimilarity($vec1, $vec2) {
    $dot = 0;
    $norm1 = 0;
    $norm2 = 0;
    for ($i = 0; $i < count($vec1); $i++) {
        $dot += $vec1[$i] * $vec2[$i];
        $norm1 += $vec1[$i] * $vec1[$i];
        $norm2 += $vec2[$i] * $vec2[$i];
    }
    $norm1 = sqrt($norm1);
    $norm2 = sqrt($norm2);
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
    <script src="/sunatt/js/face-api.min.js" onload="console.log('face-api.js loaded'); window.faceApiLoaded = true;" onerror="console.error('Failed to load face-api.js'); window.faceApiLoadError = true;"></script>
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
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-900 min-h-screen">
    <?php include 'lecturer_navbar.php'; ?>

    <div class="container mx-auto px-6 py-10">
        <header class="mb-8">
            <h1 class="text-4xl font-bold text-theme">Face Recognition Session</h1>
        </header>

        <section id="session-selection" class="bg-white p-8 rounded-xl shadow-2xl mb-8">
            <?php if (empty($sessions)): ?>
                <p class="text-red-600 font-semibold text-lg">No active sessions available. Please schedule a session.</p>
            <?php else: ?>
                <form id="session-form" class="space-y-6">
                    <div>
                        <label for="session_id" class="block text-sm font-semibold text-gray-700 mb-2">Select Session</label>
                        <select id="session_id" name="session_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-900 focus:ring-2 focus:ring-theme focus:border-transparent transition-all" required>
                            <option value="">-- Select a session --</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo htmlspecialchars($session['session_id']); ?>" 
                                      data-assignment-id="<?php echo htmlspecialchars($session['assignment_id']); ?>" 
                                      data-end-time="<?php echo htmlspecialchars($session['end_time']); ?>" 
                                      data-session-date="<?php echo htmlspecialchars($session['session_date']); ?>">
                                    <?php 
                                        $topic = !empty($session['session_topic']) ? $session['session_topic'] : 'No topic';
                                        echo htmlspecialchars("{$session['unit_name']} - {$session['session_date']} {$session['start_time']} - {$session['end_time']} at {$session['venue']} ($topic)");
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" id="start-session-btn" class="w-full bg-theme text-white font-semibold px-6 py-3 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-blue-500 focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:bg-gray-600 disabled:cursor-not-allowed">
                        Start Session
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <section id="camera-section" class="bg-white p-8 rounded-xl shadow-2xl hidden">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Live Camera Feed</h2>
            <div class="relative mb-6 flex justify-center">
                <video id="video" autoplay muted class="w-full max-w-lg rounded-lg shadow-md border border-gray-200"></video>
                <canvas id="canvas" class="absolute top: 0; left: 0;"></canvas>
            </div>
            <div class="flex space-x-4 mb-6">
                <button id="capture-btn" class="bg-blue-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all disabled:bg-gray-600 disabled:cursor-not-allowed" disabled>
                    Capture Face
                </button>
                <button id="end-session-btn" class="bg-red-600 text-white font-semibold px-6 py-3 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all disabled:bg-gray-600 disabled:cursor-not-allowed">
                    End Session
                </button>
            </div>
            <div id="status-message" class="feedback-box bg-gray-100 p-4 rounded-lg text-gray-700 shadow-inner border-box border-gray-200"></div>
            <p id="session-timer" class="mt-3 text-sm text-gray-500"></p>
        </section>
    </div>

    <script>
        let video, canvas, context, sessionActive = false, sessionTimer, modelsLoaded = false, detectionInterval;
        window.faceApiLoaded = false;

        function appendStatusMessage(message, className = 'text-gray-700') {
            // Skip UI display for "Detected 0 face(s) in frame"
            if (message.includes('Detected 0 face(s) in frame')) {
                console.log(`Status: ${message}`);
                return;
            }
            console.log(`Status: ${message}`);
            const statusDiv = document.getElementById('status-message');
            if (statusDiv) {
                const messageP = document.createElement('p');
                messageP.className = `text-sm ${className}`;
                messageP.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
                statusDiv.appendChild(messageP);
                statusDiv.scrollTop = statusDiv.scrollHeight;
            } else {
                console.error('Status message div not found:', message);
            }
        }

        async function loadModels() {
            if (modelsLoaded) return;
            try {
                appendStatusMessage('<span class="loading-spinner"></span> Loading face recognition models...', 'text-gray-500');
                console.log('Loading models from /sunatt/models');
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri('/sunatt/models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('/sunatt/models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('/sunatt/models')
                ]);
                modelsLoaded = true;
                console.log('Models loaded successfully');
                appendStatusMessage('Face recognition models loaded.', 'text-green-600');
            } catch (err) {
                console.error('Model loading error:', err);
                appendStatusMessage(`Failed to load models: ${err.message}`, 'text-red-600');
                throw err;
            }
        }

        function drawFaceRectangles(faces) {
            context.clearRect(0, 0, canvas.width, canvas.height);
            faces.forEach(face => {
                const { x, y, width, height } = face.detection.box;
                context.strokeStyle = 'green';
                context.lineWidth = 2;
                context.strokeRect(x, y, width, height);
                context.fillStyle = 'green';
                context.font = '16px Arial';
                context.fillText(`Confidence: ${(face.detection.score * 100).toFixed(1)}%`, x, y - 10);
            });
        }

        async function startRealTimeDetection() {
            if (!sessionActive || !modelsLoaded) return;
            try {
                const detections = await faceapi
                    .detectAllFaces(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                    .withFaceLandmarks();
                console.log('Detected faces:', detections.length);
                drawFaceRectangles(detections);
                appendStatusMessage(`Detected ${detections.length} face(s) in frame.`, 'text-gray-500');
            } catch (err) {
                console.error('Real-time detection error:', err);
                appendStatusMessage('Error detecting faces. Ensure face is well-lit.', 'text-red-600');
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            console.log('DOM loaded: Initializing session');

            if (window.faceApiLoadError) {
                appendStatusMessage('Error loading face-api.js. Check /sunatt/js/face-api.min.js.', 'text-red-600');
                return;
            }

            if (typeof faceapi === 'undefined') {
                console.error('faceapi not defined');
                appendStatusMessage('Failed to initialize face-api.js. Verify /sunatt/js/face-api.min.js.', 'text-red-600');
                return;
            }

            try {
                await loadModels();
            } catch (err) {
                return;
            }

            video = document.getElementById('video');
            canvas = document.getElementById('canvas');
            context = canvas.getContext('2d');
            const sessionForm = document.getElementById('session-form');
            const sessionSelect = document.getElementById('session_id');
            const startSessionBtn = document.getElementById('start-session-btn');
            const captureBtn = document.getElementById('capture-btn');
            const endSessionBtn = document.getElementById('end-session-btn');
            const cameraSection = document.getElementById('camera-section');
            const sessionSelection = document.getElementById('session-selection');
            const timerDisplay = document.getElementById('session-timer');

            if (!sessionForm) {
                console.log('No sessions available');
                appendStatusMessage('No active sessions found.', 'text-red-600');
                return;
            }

            sessionSelect.addEventListener('change', () => {
                console.log('Session selected:', sessionSelect.value);
                startSessionBtn.disabled = !sessionSelect.value;
            });

            sessionForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                console.log('Starting session');
                if (!sessionSelect.value) {
                    appendStatusMessage('Please select a session.', 'text-red-600');
                    return;
                }

                const selectedOption = sessionSelect.options[sessionSelect.selectedIndex];
                sessionEndTime = new Date(`${selectedOption.dataset.sessionDate} ${selectedOption.dataset.endTime}`);
                console.log('Session end time:', sessionEndTime);

                try {
                    console.log('Requesting camera access');
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
                    video.srcObject = stream;
                    video.style.display = 'block';
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    canvas.style.display = 'block';
                    console.log('Camera started');

                    sessionSelection.classList.add('hidden');
                    cameraSection.classList.remove('hidden');
                    captureBtn.disabled = false;
                    endSessionBtn.disabled = false;
                    sessionActive = true;
                    appendStatusMessage('Session started. Ready to capture faces.', 'text-green-600');
                    startSessionTimer();
                    detectionInterval = setInterval(startRealTimeDetection, 500); // Real-time detection every 500ms
                } catch (err) {
                    console.error('Session start error:', err);
                    appendStatusMessage(`Failed to start session: ${err.message}`, 'text-red-600');
                }
            });

            captureBtn.addEventListener('click', async () => {
                if (!sessionActive) return;

                console.log('Capture button clicked');
                // Suspend real-time detection
                clearInterval(detectionInterval);
                appendStatusMessage('<span class="loading-spinner"></span> Processing face, please hold still...', 'text-gray-500');

                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                console.log('Frame captured');

                let detection = null;
                let retries = 0;
                const maxRetries = 5;

                while (!detection && retries < maxRetries) {
                    try {
                        console.log(`Detection attempt ${retries + 1}`);
                        detection = await faceapi
                            .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
                            .withFaceLandmarks()
                            .withFaceDescriptor();
                        if (!detection || detection.detection.score < 0.5) {
                            console.log(`Retry ${retries + 1}: No face or low confidence (score: ${detection?.detection.score || 'none'})`);
                            detection = null;
                            retries++;
                            appendStatusMessage(`Retry ${retries}: No face detected. Ensure face is well-lit and centered.`, 'text-yellow-600');
                            await new Promise(resolve => setTimeout(resolve, 500));
                        } else {
                            console.log(`Face detected with confidence: ${detection.detection.score}`);
                        }
                    } catch (err) {
                        console.error('Detection error:', err);
                        retries++;
                        appendStatusMessage(`Retry ${retries}: Error detecting face: ${err.message}`, 'text-yellow-600');
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                }

                context.clearRect(0, 0, canvas.width, canvas.height);
                canvas.style.display = 'none';

                if (!detection) {
                    console.log('Failed to detect face after retries');
                    appendStatusMessage('No face detected. Ensure face is well-lit, centered, and occupies 50-70% of frame.', 'text-red-600');
                    // Resume detection
                    detectionInterval = setInterval(startRealTimeDetection, 500);
                    return;
                }

                try {
                    const faceDescriptor = Array.from(detection.descriptor);
                    console.log('Descriptor generated:', faceDescriptor.slice(0, 5), '...');
                    if (faceDescriptor.length !== 128 || !faceDescriptor.every(val => typeof val === 'number')) {
                        throw new Error('Invalid face descriptor generated.');
                    }
                    console.log('Sending face descriptor to server');

                    appendStatusMessage('Sending face data to server...', 'text-gray-500');
                    const response = await fetch('start_face_session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'process_face',
                            session_id: sessionSelect.value,
                            assignment_id: sessionSelect.options[sessionSelect.selectedIndex].dataset.assignmentId,
                            face_descriptor: JSON.stringify(faceDescriptor)
                        })
                    });

                    console.log('Server response received:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const result = await response.json();
                    console.log('Server result:', result);
                    if (result.status === 'success') {
                        appendStatusMessage(result.message, 'text-green-600');
                    } else {
                        appendStatusMessage(result.message + ' Please retry if needed.', 'text-red-600');
                    }
                } catch (err) {
                    console.error('Face processing error:', err);
                    appendStatusMessage(`Error processing face: ${err.message}. Please retry.`, 'text-red-600');
                } finally {
                    // Resume detection
                    detectionInterval = setInterval(startRealTimeDetection, 500);
                }
            });

            endSessionBtn.addEventListener('click', () => {
                console.log('Ending session');
                endSession();
            });

            function startSessionTimer() {
                console.log('Starting session timer');
                sessionTimer = setInterval(() => {
                    const now = new Date();
                    if (now >= sessionEndTime || !sessionActive) {
                        console.log('Session ended by timer');
                        endSession();
                    } else {
                        const timeLeft = Math.max(0, Math.floor((sessionEndTime - now) / 1000));
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        timerDisplay.textContent = `Session ends in ${minutes}m ${seconds}s`;
                    }
                }, 1000);
            }

            function endSession() {
                console.log('Cleaning up session');
                sessionActive = false;
                clearInterval(sessionTimer);
                clearInterval(detectionInterval);
                if (video && video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                }
                video.style.display = 'none';
                context.clearRect(0, 0, canvas.width, canvas.height);
                canvas.style.display = 'none';
                sessionSelection.classList.remove('hidden');
                cameraSection.classList.add('hidden');
                captureBtn.disabled = true;
                endSessionBtn.disabled = true;
                appendStatusMessage('Session ended.', 'text-gray-500');
                timerDisplay.textContent = '';
            }
        });
    </script>
</body>
</html>