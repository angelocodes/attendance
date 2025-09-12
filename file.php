<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Detection Snapshot with Human.js</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f0f0f0;
        }
        #container {
            position: relative;
            margin-top: 20px;
        }
        canvas {
            position: absolute;
            top: 0;
            left: 0;
        }
        #output {
            margin-top: 20px;
            max-width: 640px;
            overflow-y: auto;
            max-height: 200px;
            background: white;
            padding: 10px;
            border-radius: 5px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px;
        }
    </style>
    <!-- Load human.js ES module from CDN -->
    <script type="module">
        import Human from 'https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.esm.js';

        // Initialize Human with WebGL backend
        console.log('Initializing Human.js...');
        const config = {
            backend: 'webgl',
            modelBasePath: 'https://cdn.jsdelivr.net/npm/@vladmandic/human/models/',
            face: {
                enabled: true,
                detector: { rotation: false },
                mesh: { enabled: true },
                iris: { enabled: true },
                description: { enabled: true },
                emotion: { enabled: true }
            }
        };
        const human = new Human(config);
        let isRunning = false;
        let snapshotTaken = false;
        let snapshotCanvas;

        // Get DOM elements
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const output = document.getElementById('output');
        const startBtn = document.getElementById('startBtn');

        // Check for secure context
        function checkSecureContext() {
            const isSecure = window.isSecureContext;
            console.log('Secure context check:', isSecure ? 'Secure (HTTPS or localhost)' : 'Insecure (requires HTTPS or localhost)');
            if (!isSecure) {
                output.textContent = 'Error: Webcam access requires a secure context (HTTPS or localhost). Please serve this page over HTTPS or from localhost.';
                return false;
            }
            return true;
        }

        // Initialize webcam
        async function initWebcam() {
            if (!checkSecureContext()) return;

            console.log('Checking for webcam availability...');
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                console.log('Available video devices:', videoDevices);

                if (videoDevices.length === 0) {
                    console.error('No webcam found');
                    output.textContent = 'Error: No webcam found. Please connect a webcam and try again.';
                    return;
                }

                console.log('Attempting to access webcam...');
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }
                });
                video.srcObject = stream;
                startBtn.textContent = 'Stop Webcam';
                isRunning = true;
                console.log('Webcam initialized successfully:', stream.getVideoTracks()[0].label);
                output.textContent = 'Webcam initialized. Detecting face for snapshot...';
                detectVideo();
            } catch (err) {
                console.error('Detailed webcam error:', err.name, err.message);
                let errorMessage = 'Error accessing webcam: ';
                if (err.name === 'NotAllowedError') {
                    errorMessage += 'Permission denied. Please allow webcam access in your browser.';
                } else if (err.name === 'NotFoundError') {
                    errorMessage += 'No webcam found. Please connect a webcam.';
                } else if (err.name === 'NotReadableError') {
                    errorMessage += 'Webcam is already in use by another application.';
                } else {
                    errorMessage += err.message;
                }
                output.textContent = errorMessage;
            }
        }

        // Stop webcam
        function stopWebcam() {
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                startBtn.textContent = 'Start Webcam';
                isRunning = false;
                console.log('Webcam stopped');
                output.textContent = snapshotTaken ? output.textContent : 'Webcam stopped';
            }
        }

        // Toggle webcam on button click
        startBtn.addEventListener('click', () => {
            console.log('Button clicked, isRunning:', isRunning);
            if (isRunning) {
                stopWebcam();
            } else {
                initWebcam();
                snapshotTaken = false; // Reset for new snapshot
            }
        });

        // Detect faces and process snapshot
        async function detectVideo() {
            if (!isRunning || snapshotTaken) {
                console.log('Detection loop stopped:', !isRunning ? 'Webcam stopped' : 'Snapshot already taken');
                return;
            }
            
            try {
                console.log('Running face detection on video...');
                const result = await human.detect(video);
                const ctx = canvas.getContext('2d');
                
                // Match canvas size to video
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                // Draw video frame
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                // Process detection results
                let outputText = '';
                if (result.face && result.face.length > 0) {
                    console.log(`Detected ${result.face.length} face(s) in video`);
                    
                    // Take snapshot and stop webcam
                    snapshotTaken = true;
                    snapshotCanvas = document.createElement('canvas');
                    snapshotCanvas.width = video.videoWidth;
                    snapshotCanvas.height = video.videoHeight;
                    snapshotCanvas.getContext('2d').drawImage(video, 0, 0);
                    stopWebcam();
                    
                    // Process snapshot
                    console.log('Processing snapshot for face descriptors...');
                    const snapshotResult = await human.detect(snapshotCanvas);
                    
                    if (snapshotResult.face && snapshotResult.face.length > 0) {
                        console.log(`Detected ${snapshotResult.face.length} face(s) in snapshot`);
                        snapshotResult.face.forEach((face, index) => {
                            // Draw bounding box on displayed canvas
                            const box = face.box;
                            ctx.strokeStyle = 'red';
                            ctx.lineWidth = 2;
                            ctx.strokeRect(box[0], box[1], box[2], box[3]);

                            // Log full descriptor to console
                            console.log(`Face ${index + 1} Descriptor:`, face.embedding || 'N/A');

                            // Output basic info to page
                            outputText += `Face ${index + 1}:\n`;
                            outputText += `Confidence: ${Math.round(face.score * 100)}%\n`;
                            outputText += `Emotion: ${face.emotion ? face.emotion[0].emotion : 'N/A'}\n`;
                            outputText += `Descriptor: ${face.embedding ? face.embedding.slice(0, 5).join(', ') + '...' : 'N/A'}\n\n`;
                        });
                    } else {
                        outputText = 'No faces detected in snapshot';
                        console.log('No faces detected in snapshot');
                    }
                } else {
                    outputText = 'No faces detected in video. Keep facing the camera.';
                    console.log('No faces detected in video');
                }

                output.textContent = outputText;
                // Continue detection loop until snapshot is taken
                if (!snapshotTaken) {
                    requestAnimationFrame(detectVideo);
                }
            } catch (err) {
                console.error('Error in detection:', err);
                output.textContent = 'Error in detection: ' + err.message;
            }
        }

        // Initialize Human.js
        console.log('Loading Human.js models...');
        human.load().then(() => {
            console.log('Human.js models loaded successfully');
            output.textContent = 'Human.js loaded. Click "Start Webcam" to begin.';
        }).catch(err => {
            console.error('Error loading Human.js:', err);
            output.textContent = 'Error loading Human.js: ' + err.message;
        });
    </script>
</head>
<body>
    <h1>Face Detection Snapshot with Webcam</h1>
    <button id="startBtn">Start Webcam</button>
    <div id="container">
        <video id="video" width="640" height="480" autoplay muted></video>
        <canvas id="canvas"></canvas>
    </div>
    <div id="output">Face descriptors will appear here...</div>
</body>
</html>