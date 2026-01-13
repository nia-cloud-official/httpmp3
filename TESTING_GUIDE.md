# Protocol % Testing Guide

## 🧪 **Manual Testing Steps**

### **Step 1: Verify Server is Running**
```bash
curl http://localhost:8080/api/frequencies
```
Should return JSON with frequency mappings.

### **Step 2: Test Audio Generation**
```bash
# Generate audio from HTML
curl -o test.wav http://localhost:8080/audio/simple.html

# Check file was created
ls -la test.wav
```

### **Step 3: Test the Production Client**

1. **Open Browser**: Navigate to `http://localhost:8080/production_client.html`

2. **Test Server Stream**:
   - Select "Server Stream" as source
   - Enter "simple.html" as filename
   - Click "Start Decoding"
   - Watch the HTML reconstruct in real-time

3. **Test Audio File**:
   - Select "Audio File" as source
   - Upload the `production_sample.wav` file we generated
   - Click "Start Decoding"
   - Observe waveform, spectrogram, and HTML preview

### **Step 4: Test Command Line Encoder**
```bash
# Basic encoding
php production_encode.php simple.html output.wav

# Verbose mode
php production_encode.php --verbose simple.html output_verbose.wav

# Ultrasonic mode
php production_encode.php --ultrasonic simple.html output_ultrasonic.wav
```

### **Step 5: Performance Testing**
```bash
# Run the test suite
php test_production.php

# Load testing (if you have Apache Bench)
ab -n 50 -c 5 http://localhost:8080/audio/simple.html
```

## 🎯 **Expected Results**

### **Audio Generation**
- WAV files should be ~1MB per KB of HTML
- Encoding should complete in 5-15 seconds
- Audio duration should be 10-30 seconds for small HTML

### **Client Decoding**
- Frequency detection should show values like 440Hz, 660Hz, 880Hz
- HTML elements should appear progressively
- Waveform should show clear signal patterns
- Spectrogram should show frequency bands

### **API Responses**
- `/api/frequencies` returns frequency map
- `/audio/*.html` returns WAV files
- `/api/encode/*.html` returns encoding metadata

## 🔍 **Troubleshooting**

### **Server Won't Start**
```bash
# Check if port is in use
netstat -tlnp | grep 8080

# Kill existing processes
pkill -f production_server.php

# Restart server
php production_server.php
```

### **Audio Generation Fails**
- Check HTML file exists and is readable
- Verify sufficient disk space
- Check server logs for errors

### **Client Can't Decode**
- Ensure microphone permissions granted
- Try different FFT sizes (2048, 4096, 8192)
- Adjust detection threshold
- Check browser console for errors

### **No Audio Output**
- Verify speakers/headphones connected
- Check system audio settings
- Try different browsers
- Test with simple.html first

## 📊 **Performance Benchmarks**

### **Typical Performance**
- **Encoding Speed**: 2-5 bytes/second
- **File Size Ratio**: 1KB HTML → ~1MB WAV
- **Decoding Accuracy**: >95% with good audio quality
- **Latency**: <500ms for real-time streaming

### **Optimization Tips**
- Use smaller block sizes for faster encoding
- Enable ultrasonic mode to avoid audio interference
- Increase FFT size for better frequency resolution
- Use production mode for best performance

## 🚀 **Production Deployment**

### **Docker Testing**
```bash
# Build and run
docker build -t protocol-sound .
docker run -p 8080:8080 protocol-sound

# Or use docker-compose
docker-compose up -d
```

### **Load Testing**
```bash
# Test concurrent connections
for i in {1..10}; do
  curl http://localhost:8080/audio/simple.html > /dev/null &
done
wait
```

### **Monitoring**
- Check server logs for errors
- Monitor memory usage during encoding
- Test with various HTML file sizes
- Verify CORS headers for web clients

## ✅ **Success Criteria**

The system is working correctly if:
- [ ] Server starts without errors
- [ ] API endpoints return valid responses
- [ ] Audio files are generated successfully
- [ ] Client can decode audio and reconstruct HTML
- [ ] Performance meets expected benchmarks
- [ ] Error handling works for invalid inputs

## 🎵 **Audio Quality Check**

### **Manual Audio Verification**
1. Play generated WAV file
2. Should hear distinct tones and patterns
3. Musical mode: Harmonic, pleasant tones
4. Ultrasonic mode: Silent (>20kHz)
5. No distortion or clipping

### **Frequency Analysis**
Use audio analysis tools to verify:
- Correct frequencies are present
- Signal-to-noise ratio is adequate
- No unwanted harmonics or artifacts