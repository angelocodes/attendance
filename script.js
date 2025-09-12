async function loadModels() {
    const status = document.getElementById('status');
    const progress = document.getElementById('progress');
    const registerBtn = document.getElementById('register');
    const identifyBtn = document.getElementById('identify');

    progress.innerText = 'Loading face detection models...';
    console.log('Attempting to load models from /face_recognition/models');
    try {
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri('/face_recognition/models').catch(e => { throw new Error('SSD Mobilenetv1 failed: ' + e.message); }),
            faceapi.nets.faceLandmark68Net.loadFromUri('/face_recognition/models').catch(e => { throw new Error('Face Landmark failed: ' + e.message); }),
            faceapi.nets.faceRecognitionNet.loadFromUri('/face_recognition/models').catch(e => { throw new Error('Face Recognition failed: ' + e.message); })
        ]);
        status.innerText = 'Models loaded successfully!';
        progress.innerText = '';
        registerBtn.disabled = false;
        identifyBtn.disabled = false;
        console.log('Face-api.js models loaded successfully from /face_recognition/models');
    } catch (error) {
        status.innerText = 'Error loading models: ' + error.message;
        progress.innerText = '';
        console.error('Model loading error:', error);
    }
}

async function startVideo() {
    const video = document.getElementById('video');
    const status = document.getElementById('status');
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        video.onloadedmetadata = () => {
            console.log('Video stream started');
            status.innerText = 'Camera ready!';
        };
    } catch (error) {
        status.innerText = 'Error accessing camera: ' + error.message;
        console.error('Camera error:', error);
    }
}

async function registerFace() {
    const username = document.getElementById('username').value.trim();
    const status = document.getElementById('status');
    const progress = document.getElementById('progress');
    const registerBtn = document.getElementById('register');

    if (!username) {
        status.innerText = 'Please enter a username!';
        console.warn('Registration attempted without username');
        return;
    }

    registerBtn.disabled = true;
    progress.innerText = 'Capturing face...';
    console.log('Starting face registration for:', username);

    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    try {
        const detections = await faceapi.detectSingleFace(video)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detections) {
            status.innerText = 'No face detected! Please ensure your face is visible.';
            progress.innerText = '';
            console.warn('No face detected during registration');
            registerBtn.disabled = false;
            return;
        }

        progress.innerText = 'Processing face descriptor...';
        const dataURL = canvas.toDataURL('image/png');
        const descriptor = JSON.stringify(Array.from(detections.descriptor));

        progress.innerText = 'Sending data to server...';
        console.log('Sending face data to server for:', username);

        const response = await fetch('register.php', {
            method: 'POST',
            body: JSON.stringify({ username, image: dataURL, descriptor }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();
        status.innerText = result.message;
        progress.innerText = '';
        console.log('Server response:', result);

        if (result.status) {
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            registerBtn.disabled = false;
        }
    } catch (error) {
        status.innerText = 'Error registering face: ' + error.message;
        progress.innerText = '';
        console.error('Registration error:', error);
        registerBtn.disabled = false;
    }
}

async function identifyFace() {
    const status = document.getElementById('status');
    const progress = document.getElementById('progress');
    const identifyBtn = document.getElementById('identify');

    identifyBtn.disabled = true;
    progress.innerText = 'Capturing face for identification...';
    console.log('Starting face identification');

    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    try {
        const detections = await faceapi.detectSingleFace(video)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detections) {
            status.innerText = 'No face detected! Please ensure your face is visible.';
            progress.innerText = '';
            console.warn('No face detected during identification');
            identifyBtn.disabled = false;
            return;
        }

        progress.innerText = 'Processing face descriptor...';
        const descriptor = JSON.stringify(Array.from(detections.descriptor));

        progress.innerText = 'Sending data to server...';
        console.log('Sending face data for identification');

        const response = await fetch('identify.php', {
            method: 'POST',
            body: JSON.stringify({ descriptor }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();
        status.innerText = result.message;
        progress.innerText = '';
        console.log('Identification response:', result);
        identifyBtn.disabled = false;
    } catch (error) {
        status.innerText = 'Error identifying face: ' + error.message;
        progress.innerText = '';
        console.error('Identification error:', error);
        identifyBtn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing...');
    loadModels();
    startVideo();

    const registerBtn = document.getElementById('register');
    const identifyBtn = document.getElementById('identify');

    registerBtn.addEventListener('click', () => {
        console.log('Register button clicked');
        registerFace();
    });

    identifyBtn.addEventListener('click', () => {
        console.log('Identify button clicked');
        identifyFace();
    });
});