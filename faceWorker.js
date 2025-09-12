self.importScripts('/sunatt/js/face-api.min.js', 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core@3.21.0/dist/tf-core.min.js');

let modelsLoaded = false;

function log(message) {
    self.postMessage({ type: 'log', data: message });
}

// Configure TensorFlow.js environment for Web Worker
if (typeof tf !== 'undefined') {
    tf.env().set('WEBGL_PACK', false); // Optimize for Web Worker
    tf.env().set('IS_BROWSER', true); // Mimic browser environment
    log('Worker: TensorFlow.js environment configured');
} else {
    log('Worker: TensorFlow.js not loaded');
}

self.addEventListener('message', async function(e) {
    const { action, modelsPath, imageData, width, height } = e.data;

    if (action === 'initialize') {
        try {
            log('Worker: Loading face-api.js models');
            await Promise.all([
                faceapi.nets.ssdMobilenetv1.loadFromUri(modelsPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelsPath)
            ]);
            modelsLoaded = true;
            log('Worker: Models loaded successfully');
            self.postMessage({ type: 'initialized' });
        } catch (err) {
            log('Worker: Model loading error: ' + err.message);
            self.postMessage({ type: 'error', error: err.message });
        }
    } else if (action === 'detect' && modelsLoaded) {
        try {
            log('Worker: Starting face detection');
            const canvas = new OffscreenCanvas(width, height);
            const ctx = canvas.getContext('2d');
            ctx.putImageData(imageData, 0, 0);

            log('Worker: Running SSD MobileNet detection');
            const detection = await faceapi
                .detectSingleFace(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.7 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection || detection.detection.score < 0.7) {
                log('Worker: No face detected or low confidence (score: ' + (detection?.detection.score || 'none') + ')');
                self.postMessage({ type: 'detection', data: null });
                return;
            }

            log('Worker: Face detected with confidence: ' + detection.detection.score);
            self.postMessage({ type: 'detection', data: { descriptor: detection.descriptor } });
        } catch (err) {
            log('Worker: Detection error: ' + err.message);
            self.postMessage({ type: 'error', error: err.message });
        }
    }
});