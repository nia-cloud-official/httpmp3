class HTTPMP3Decoder {
    constructor() {
        this.audioContext = null;
        this.analyser = null;
        this.source = null;
        this.isDecoding = false;
        this.frequencies = {};
        this.decodedHTML = '';
        this.elementCount = 0;
        this.lastFrequency = 0;
        this.state = 'waiting';
        this.currentSource = 'file';
        this.micStream = null;
        
        this.initializeEventListeners();
        this.loadFrequencyMap();
    }
    
    async loadFrequencyMap() {
        this.frequencies = {
            frame_start: [440, 554, 659],
            frame_end: [440, 349, 262],
            http_200: [523, 659, 784],
            html_open: 440,
            html_close: 415,
            head_open: 660,
            head_close: 622,
            body_open: 880,
            body_close: 831,
            title: [523, 659, 784, 1047],
            h1: 1047,
            h2: 932,
            h3: 831,
            p: 698,
            div: 622,
            a: [392, 494, 587],
            fsk_0: 2000,
            fsk_1: 2400,
            sync: 2800,
            block_start: 1760,
            block_end: 1568
        };
        this.log('Frequency map loaded', 'success');
    }
    
    initializeEventListeners() {
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', this.switchSource.bind(this));
        });
        
        // Control buttons
        document.getElementById('startBtn').addEventListener('click', this.startDecoding.bind(this));
        document.getElementById('stopBtn').addEventListener('click', this.stopDecoding.bind(this));
        document.getElementById('clearBtn').addEventListener('click', this.clearResults.bind(this));
        
        // Settings
        document.getElementById('threshold').addEventListener('input', this.updateSettings.bind(this));
        document.getElementById('smoothing').addEventListener('input', this.updateSettings.bind(this));
    }
    
    switchSource(e) {
        // Update active tab
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        e.target.classList.add('active');
        
        // Hide all source panels
        document.querySelectorAll('.source-panel').forEach(panel => {
            panel.style.display = 'none';
        });
        
        // Show selected source panel
        this.currentSource = e.target.dataset.source;
        document.getElementById(this.currentSource + 'Source').style.display = 'block';
        
        this.log(`Switched to ${this.currentSource} input`, 'info');
    }
    
    updateSettings() {
        if (this.analyser) {
            const smoothing = parseFloat(document.getElementById('smoothing').value);
            this.analyser.smoothingTimeConstant = smoothing;
        }
    }
    
    async startDecoding() {
        try {
            this.updateStatus('Initializing audio context...', 'info');
            
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            if (this.audioContext.state === 'suspended') {
                await this.audioContext.resume();
            }
            
            switch (this.currentSource) {
                case 'file':
                    await this.loadAudioFile();
                    break;
                case 'microphone':
                case 'speaker':
                    await this.startMicrophoneCapture();
                    break;
            }
            
            this.setupAnalyser();
            this.isDecoding = true;
            this.startAnalysis();
            
            document.getElementById('startBtn').disabled = true;
            document.getElementById('stopBtn').disabled = false;
            
            this.updateStatus('🎵 Decoding audio...', 'success');
            
        } catch (error) {
            this.updateStatus('Error: ' + error.message, 'error');
            this.log('Decoder error: ' + error.message, 'error');
        }
    }
    
    async loadAudioFile() {
        const fileInput = document.getElementById('audioFile');
        if (!fileInput.files[0]) {
            throw new Error('Please select an audio file');
        }
        
        this.updateStatus('Loading audio file...', 'info');
        
        const arrayBuffer = await fileInput.files[0].arrayBuffer();
        const audioBuffer = await this.audioContext.decodeAudioData(arrayBuffer);
        
        this.source = this.audioContext.createBufferSource();
        this.source.buffer = audioBuffer;
        this.source.loop = false;
        
        this.log(`Loaded audio file: ${fileInput.files[0].name}`, 'success');
    }
    
    async startMicrophoneCapture() {
        this.updateStatus('Requesting microphone access...', 'info');
        
        try {
            this.micStream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    sampleRate: 44100,
                    channelCount: 1,
                    echoCancellation: false,
                    noiseSuppression: false,
                    autoGainControl: false
                } 
            });
            
            this.source = this.audioContext.createMediaStreamSource(this.micStream);
            this.log('🎤 Microphone capture started - listening for audio signals', 'success');
            
            // Start microphone visualizer
            this.startMicrophoneVisualizer();
            
        } catch (error) {
            throw new Error('Microphone access denied or not available: ' + error.message);
        }
    }
    
    startMicrophoneVisualizer() {
        const canvas = document.getElementById('micVisualizer');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const width = canvas.width = canvas.offsetWidth * window.devicePixelRatio;
        const height = canvas.height = canvas.offsetHeight * window.devicePixelRatio;
        
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        
        const visualize = () => {
            if (!this.isDecoding || !this.analyser) return;
            
            const bufferLength = this.analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            this.analyser.getByteFrequencyData(dataArray);
            
            ctx.fillStyle = '#0d1117';
            ctx.fillRect(0, 0, width / window.devicePixelRatio, height / window.devicePixelRatio);
            
            const barWidth = (width / window.devicePixelRatio) / bufferLength * 2.5;
            let barHeight;
            let x = 0;
            
            for (let i = 0; i < bufferLength; i++) {
                barHeight = (dataArray[i] / 255) * (height / window.devicePixelRatio);
                
                const hue = (i / bufferLength) * 360;
                ctx.fillStyle = `hsl(${hue}, 70%, 50%)`;
                ctx.fillRect(x, (height / window.devicePixelRatio) - barHeight, barWidth, barHeight);
                
                x += barWidth + 1;
            }
            
            requestAnimationFrame(visualize);
        };
        
        visualize();
    }
    
    setupAnalyser() {
        const smoothing = parseFloat(document.getElementById('smoothing').value);
        
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 4096;
        this.analyser.smoothingTimeConstant = smoothing;
        this.analyser.minDecibels = -90;
        this.analyser.maxDecibels = -10;
        
        if (this.source) {
            this.source.connect(this.analyser);
            
            if (this.source.start) {
                this.source.start(0);
            }
        }
    }
    
    startAnalysis() {
        const bufferLength = this.analyser.frequencyBinCount;
        const frequencyData = new Uint8Array(bufferLength);
        const timeData = new Uint8Array(bufferLength);
        
        const analyze = () => {
            if (!this.isDecoding) return;
            
            this.analyser.getByteFrequencyData(frequencyData);
            this.analyser.getByteTimeDomainData(timeData);
            
            this.updateVisualizations(frequencyData, timeData);
            this.processAudioData(frequencyData);
            
            requestAnimationFrame(analyze);
        };
        
        analyze();
    }
    
    updateVisualizations(frequencyData, timeData) {
        this.drawWaveform(timeData);
        this.drawSpectrogram(frequencyData);
        this.updateMetrics(frequencyData);
    }
    
    drawWaveform(timeData) {
        const canvas = document.getElementById('waveformCanvas');
        const ctx = canvas.getContext('2d');
        const width = canvas.width = canvas.offsetWidth * window.devicePixelRatio;
        const height = canvas.height = canvas.offsetHeight * window.devicePixelRatio;
        
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        
        ctx.fillStyle = '#0d1117';
        ctx.fillRect(0, 0, width, height);
        
        ctx.strokeStyle = '#58a6ff';
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        const sliceWidth = (width / window.devicePixelRatio) / timeData.length;
        let x = 0;
        
        for (let i = 0; i < timeData.length; i++) {
            const v = timeData[i] / 128.0;
            const y = v * (height / window.devicePixelRatio) / 2;
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
            
            x += sliceWidth;
        }
        
        ctx.stroke();
    }
    
    drawSpectrogram(frequencyData) {
        const canvas = document.getElementById('spectrogramCanvas');
        const ctx = canvas.getContext('2d');
        const width = canvas.width = canvas.offsetWidth * window.devicePixelRatio;
        const height = canvas.height = canvas.offsetHeight * window.devicePixelRatio;
        
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        
        // Shift existing data left
        const imageData = ctx.getImageData(1, 0, width - 1, height);
        ctx.putImageData(imageData, 0, 0);
        
        // Draw new column
        const displayWidth = width / window.devicePixelRatio;
        const displayHeight = height / window.devicePixelRatio;
        
        for (let i = 0; i < frequencyData.length; i++) {
            const value = frequencyData[i];
            const percent = value / 255;
            
            const hue = (1 - percent) * 240;
            const saturation = 100;
            const lightness = percent * 50;
            
            ctx.fillStyle = `hsl(${hue}, ${saturation}%, ${lightness}%)`;
            
            const y = displayHeight - (i / frequencyData.length) * displayHeight;
            const barHeight = displayHeight / frequencyData.length;
            
            ctx.fillRect(displayWidth - 1, y, 1, barHeight);
        }
    }
    
    updateMetrics(frequencyData) {
        let maxIndex = 0;
        let maxValue = 0;
        
        for (let i = 0; i < frequencyData.length; i++) {
            if (frequencyData[i] > maxValue) {
                maxValue = frequencyData[i];
                maxIndex = i;
            }
        }
        
        const frequency = (maxIndex * this.audioContext.sampleRate) / (this.analyser.fftSize * 2);
        
        document.getElementById('dominantFreq').textContent = frequency.toFixed(1);
        document.getElementById('decodedElements').textContent = this.elementCount;
    }
    
    processAudioData(frequencyData) {
        const threshold = parseInt(document.getElementById('threshold').value);
        const dominantFreq = this.getDominantFrequency(frequencyData, threshold);
        
        if (dominantFreq > 0 && Math.abs(dominantFreq - this.lastFrequency) > 50) {
            this.lastFrequency = dominantFreq;
            
            const element = this.identifyElement(dominantFreq);
            
            if (element && this.shouldProcessElement(element)) {
                this.processElement(element, dominantFreq);
            }
        }
    }
    
    shouldProcessElement(element) {
        const now = Date.now();
        if (!this.lastElementTime) this.lastElementTime = {};
        
        if (this.lastElementTime[element] && (now - this.lastElementTime[element]) < 500) {
            return false;
        }
        
        this.lastElementTime[element] = now;
        return true;
    }
    
    getDominantFrequency(frequencyData, threshold) {
        let maxIndex = 0;
        let maxValue = 0;
        
        for (let i = 0; i < frequencyData.length; i++) {
            if (frequencyData[i] > maxValue && frequencyData[i] > threshold) {
                maxValue = frequencyData[i];
                maxIndex = i;
            }
        }
        
        if (maxValue < threshold) return 0;
        
        return (maxIndex * this.audioContext.sampleRate) / (this.analyser.fftSize * 2);
    }
    
    identifyElement(frequency) {
        const tolerance = 30;
        let bestMatch = null;
        let bestDistance = Infinity;
        
        for (const [element, freq] of Object.entries(this.frequencies)) {
            if (Array.isArray(freq)) {
                for (const f of freq) {
                    const distance = Math.abs(frequency - f);
                    if (distance < tolerance && distance < bestDistance) {
                        bestMatch = element;
                        bestDistance = distance;
                    }
                }
            } else {
                const distance = Math.abs(frequency - freq);
                if (distance < tolerance && distance < bestDistance) {
                    bestMatch = element;
                    bestDistance = distance;
                }
            }
        }
        
        return bestMatch;
    }
    
    processElement(element, frequency) {
        this.elementCount++;
        this.log(`${frequency.toFixed(1)}Hz → ${element}`, 'element');
        
        // Initialize HTML structure if not already done
        if (!this.decodedHTML || this.decodedHTML.length === 0) {
            this.decodedHTML = '<!DOCTYPE html>\n<html>\n<head>\n<title>HTTPMP3 Decoded Website</title>\n<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f5f5;}</style>\n</head>\n<body>\n<h1>🎵 Website Decoded from Audio!</h1>\n';
            this.state = 'decoding';
            this.log('HTML structure initialized', 'success');
            this.updateHTMLPreview();
        }
        
        switch (element) {
            case 'frame_start':
                if (this.state !== 'decoding') {
                    this.decodedHTML = '<!DOCTYPE html>\n<html>\n<head>\n<title>Frame Started</title>\n</head>\n<body>\n<h1>🚀 Transmission Started</h1>\n';
                    this.state = 'decoding';
                    this.log('Frame started - building HTML structure', 'success');
                }
                this.decodedHTML += '<div style="background:#e8f5e8;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #28a745;">✅ Frame initialization complete</div>\n';
                this.updateHTMLPreview();
                break;
                
            case 'http_200':
                this.log('HTTP 200 OK received', 'success');
                if (this.state === 'decoding') {
                    this.decodedHTML += '<div style="background:#d4edda;color:#155724;padding:15px;margin:10px 0;border-radius:8px;">📡 HTTP 200 OK - Connection successful</div>\n';
                    this.updateHTMLPreview();
                }
                break;
                
            case 'body_open':
                if (this.state === 'decoding') {
                    this.decodedHTML += '<div style="background:#cce5ff;color:#004085;padding:20px;margin:15px 0;border-radius:12px;box-shadow:0 2px 4px rgba(0,0,0,0.1);">\n';
                    this.decodedHTML += '<h2>🎯 Body Section Detected!</h2>\n';
                    this.decodedHTML += '<p>This content was successfully decoded from audio frequencies transmitted at <strong>' + frequency.toFixed(1) + 'Hz</strong>.</p>\n';
                    this.decodedHTML += '<p><em>Decoded at: ' + new Date().toLocaleTimeString() + '</em></p>\n';
                    this.decodedHTML += '</div>\n';
                    this.updateHTMLPreview();
                }
                break;
                
            case 'html_close':
                if (this.state === 'decoding') {
                    this.decodedHTML += '<div style="background:#28a745;color:white;padding:25px;margin:15px 0;border-radius:12px;text-align:center;box-shadow:0 4px 8px rgba(0,0,0,0.2);">\n';
                    this.decodedHTML += '<h2>🎉 Transmission Complete!</h2>\n';
                    this.decodedHTML += '<p>Successfully decoded complete website from audio signal.</p>\n';
                    this.decodedHTML += '<p><strong>Total elements decoded:</strong> ' + this.elementCount + '</p>\n';
                    this.decodedHTML += '<p><em>Completed at ' + new Date().toLocaleTimeString() + '</em></p>\n';
                    this.decodedHTML += '</div>\n';
                    
                    if (!this.decodedHTML.includes('</body>')) {
                        this.decodedHTML += '</body>\n';
                    }
                    if (!this.decodedHTML.includes('</html>')) {
                        this.decodedHTML += '</html>';
                    }
                    this.log('HTML document completed!', 'success');
                    this.updateHTMLPreview();
                }
                break;
                
            case 'frame_end':
                this.state = 'complete';
                this.log('Frame completed successfully', 'success');
                this.updateStatus('✨ Decoding completed - Website successfully reconstructed!', 'success');
                break;
                
            default:
                if (this.state === 'decoding') {
                    this.decodedHTML += `<div style="background:#fff3cd;color:#856404;padding:12px;margin:8px 0;border-radius:6px;border-left:3px solid #ffc107;">🔍 Signal: ${element} detected at ${frequency.toFixed(1)}Hz</div>\n`;
                    this.updateHTMLPreview();
                }
                this.log(`Processing: ${element}`, 'info');
        }
    }
    
    updateHTMLPreview() {
        const iframe = document.getElementById('htmlPreview');
        const placeholder = document.getElementById('htmlPreviewPlaceholder');
        
        this.log(`Updating preview - HTML length: ${this.decodedHTML ? this.decodedHTML.length : 0}`, 'info');
        
        if (this.decodedHTML && this.decodedHTML.trim().length > 0) {
            placeholder.style.display = 'none';
            iframe.style.display = 'block';
            
            try {
                iframe.srcdoc = this.decodedHTML;
                this.log('Website updated in preview', 'success');
                return;
            } catch (error) {
                this.log('Preview error: ' + error.message, 'error');
            }
            
            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                if (iframeDoc) {
                    iframeDoc.open();
                    iframeDoc.write(this.decodedHTML);
                    iframeDoc.close();
                    this.log('Website rendered via document.write', 'success');
                    return;
                }
            } catch (error) {
                this.log('Fallback render failed: ' + error.message, 'error');
            }
            
            iframe.style.display = 'none';
            placeholder.style.display = 'block';
            placeholder.innerHTML = `
                <div style="background: #0d1117; color: #c9d1d9; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 12px; line-height: 1.4; max-height: 350px; overflow-y: auto;">
                    <div style="color: #58a6ff; margin-bottom: 15px; font-weight: bold;">📄 Decoded HTML Source:</div>
                    <pre style="margin: 0; white-space: pre-wrap;">${this.decodedHTML.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>
                </div>
            `;
            this.log('Showing HTML source (fallback)', 'info');
        } else {
            placeholder.style.display = 'block';
            iframe.style.display = 'none';
        }
    }
    
    log(message, type = 'info') {
        const logContainer = document.getElementById('decoderLog');
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        
        const timestamp = new Date().toLocaleTimeString();
        const typeClass = type === 'element' ? 'log-element' : 
                         type === 'success' ? 'log-element' :
                         type === 'error' ? 'log-frequency' : 'log-text';
        
        entry.innerHTML = `
            <span class="log-timestamp">${timestamp}</span> - 
            <span class="${typeClass}">${message}</span>
        `;
        
        logContainer.appendChild(entry);
        logContainer.scrollTop = logContainer.scrollHeight;
        
        while (logContainer.children.length > 100) {
            logContainer.removeChild(logContainer.firstChild);
        }
    }
    
    updateStatus(message, type = 'info') {
        const status = document.getElementById('status');
        status.textContent = message;
        status.className = `status ${type}`;
    }
    
    stopDecoding() {
        this.isDecoding = false;
        
        if (this.micStream) {
            this.micStream.getTracks().forEach(track => track.stop());
            this.micStream = null;
        }
        
        if (this.source && this.source.mediaStream) {
            this.source.mediaStream.getTracks().forEach(track => track.stop());
        }
        
        if (this.source && this.source.stop) {
            this.source.stop();
        }
        
        document.getElementById('startBtn').disabled = false;
        document.getElementById('stopBtn').disabled = true;
        
        this.updateStatus('⏹️ Decoding stopped', 'info');
        this.log('Decoder stopped by user', 'info');
    }
    
    clearResults() {
        this.decodedHTML = '';
        this.elementCount = 0;
        this.state = 'waiting';
        
        // Clear preview
        document.getElementById('htmlPreview').style.display = 'none';
        document.getElementById('htmlPreviewPlaceholder').style.display = 'block';
        document.getElementById('htmlPreviewPlaceholder').innerHTML = `
            <p style="color: #6e7681; font-style: italic; text-align: center; margin-top: 80px;">
                🎵 Decoded website will appear here in real-time as audio is processed...
            </p>
        `;
        
        // Clear log
        document.getElementById('decoderLog').innerHTML = `
            <div class="log-entry">
                <span class="log-timestamp">Ready</span> - 
                <span class="log-text">Waiting for audio input...</span>
            </div>
        `;
        
        // Reset metrics
        document.getElementById('dominantFreq').textContent = '-';
        document.getElementById('decodedElements').textContent = '0';
        
        this.updateStatus('🗑️ Results cleared - Ready for new audio', 'info');
        this.log('Results cleared by user', 'info');
    }
}

// Initialize when page loads
window.addEventListener('load', () => {
    new HTTPMP3Decoder();
});