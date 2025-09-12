<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Minimal Human.js Face Detection</title>
  <style>
    body {
      background: #111;
      color: #eee;
      font-family: sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
    }
    video,
    canvas {
      border: 1px solid #0f0;
      border-radius: 10px;
      width: 480px;
      max-width: 100%;
    }
    .error {
      color: red;
      margin-top: 10px;
    }
  </style>

  <!-- Load Human.js (offline, assuming ESM version) -->
  <script src="/att/js/human.js"></script>
</head>
<body>
  <h1>Face Detection</h1>
  <video id="video" autoplay muted playsinline></video>
  <canvas id="canvas"></canvas>
  <div id="error" class="error"></div>

  <script>
    const humanConfig = {
      modelBasePath: '/att/models/',
      face: { enabled: true },
      backend: 'webgl' // Prefer WebGL, falls back to WASM if needed
    };

    async function start() {
      const errorDiv = document.getElementById('error');

      // Debug Human constructor
      if (!window.Human) {
        errorDiv.textContent = 'Error: Human.js not loaded. Check /att/js/human.js path.';
        console.error('Human.js not loaded');
        return;
      }
      console.log('window.Human:', window.Human);

      // Use ESM workaround
      const HumanConstructor = window.Human.Human || window.Human.default;
      if (typeof HumanConstructor !== 'function' || !HumanConstructor.prototype) {
        errorDiv.textContent = 'Error: Human constructor not found. Replace /att/js/human.js with human.iife.js from @vladmandic/human.';
        console.error('Human constructor not found:', window.Human);
        return;
      }

      let human;
      try {
        human = new HumanConstructor(humanConfig);
        console.log('Human instance created:', human);
      } catch (e) {
        errorDiv.textContent = 'Error: Failed to create Human instance';
        console.error('Failed to create Human instance:', e);
        return;
      }

      try {
        await human.load();
        console.log('Human.js models loaded');
      } catch (e) {
        errorDiv.textContent = 'Error: Failed to load models from /att/models/. Ensure BlazeFace files are present.';
        console.error('Failed to load models:', e);
        return;
      }

      try {
        await human.warmup();
        console.log('Human.js warmed up');
      } catch (e) {
        errorDiv.textContent = 'Error: Human.js warmup failed';
        console.error('Warmup failed:', e);
        return;
      }

      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      try {
        // Use Human.js webcam helper
        await human.webcam.start({ video: video, crop: true });
        console.log('Webcam started:', human.webcam.element);
      } catch (e) {
        errorDiv.textContent = 'Error: Failed to start webcam. Ensure permission is granted and running on localhost/HTTPS.';
        console.error('Webcam start failed:', e);
        return;
      }

      // Set canvas size
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      // Start detection and drawing loop
      async function drawResults() {
        try {
          const interpolated = human.next(); // Get smoothened results
          const ctx = canvas.getContext('2d');
          ctx.clearRect(0, 0, canvas.width, canvas.height);
          // Draw webcam frame
          human.draw.canvas(human.webcam.element, canvas);
          // Draw face detection results
          human.draw.face(canvas, interpolated.face);
        } catch (e) {
          console.error('Draw failed:', e);
        }
        requestAnimationFrame(drawResults);
      }

      // Start video detection
      try {
        await human.video(human.webcam.element);
        console.log('Video detection started');
      } catch (e) {
        errorDiv.textContent = 'Error: Failed to start video detection';
        console.error('Video detection failed:', e);
        return;
      }

      drawResults(); // Start drawing loop
    }

    window.onload = start;
  </script>
</body>
</html>
