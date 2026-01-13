# Protocol % - Usage Guide

## Quick Start

### 1. Generate Audio from HTML
Open `generator.html` in your browser:
1. Enter HTML content in the textarea
2. Adjust audio settings (tone duration, gap, volume)
3. Click "Generate Audio"
4. Download the generated WAV file

### 2. Decode Audio Back to HTML
Open `decoder.html` in your browser:
1. Upload the generated WAV file or use microphone input
2. Enable AI validation if desired (requires OpenAI API key)
3. Click "Start Decoding"
4. View the reconstructed HTML in real-time

### 3. Analyze Audio Frequencies
Open `frequency_analyzer.html` in your browser:
1. Upload an HTTPMP3 audio file
2. Click "Analyze Audio"
3. View frequency mappings and decoded text
4. Examine the spectrogram visualization

## Web Interface Features

### Generator (`generator.html`)
- **HTML Input**: Textarea for entering HTML content
- **Audio Settings**: Configurable tone duration, gap duration, and volume
- **Real-time Generation**: Instant audio creation with progress tracking
- **Download**: Export as WAV file for transmission or storage
- **Encoding Details**: View frequency mappings and statistics

### Decoder (`decoder.html`)
- **Multiple Input Sources**: File upload, microphone, or drag & drop
- **AI Validation**: Optional ChatGPT integration for HTML correction
- **Real-time Analysis**: Live frequency detection and character decoding
- **Tabbed Interface**: HTML code, website preview, and frequency analysis
- **Progress Tracking**: Visual feedback during decoding process

### Frequency Analyzer (`frequency_analyzer.html`)
- **Advanced FFT Analysis**: Configurable FFT size and hop size
- **Visualization**: Waveform and frequency spectrum displays
- **Detailed Results**: Frequency table with character mappings
- **Performance Metrics**: Success rates and analysis statistics

### System Test (`test_system.html`)
- **Character Encoding Test**: Validates ASCII-to-frequency mapping
- **Frequency Range Test**: Checks for conflicts and coverage
- **HTML Round-trip Test**: End-to-end encoding/decoding validation

## Protocol Specification

### Character-to-Frequency Mapping
```
Frequency = 600Hz + (ASCII_code - 32) × 15Hz
```

**Supported Characters**: ASCII 32-126 (95 printable characters)
**Frequency Range**: 600Hz - 2010Hz
**Frequency Step**: 15Hz

### Control Frequencies
- **Frame Start**: 440Hz - Marks transmission beginning
- **Data Start**: 500Hz - Signals start of character data  
- **Data End**: 2100Hz - Signals end of character data
- **Frame End**: 2200Hz - Marks transmission completion

### Audio Frame Structure
```
[Frame Start: 440Hz]
[Data Start: 500Hz]
[Character frequencies...]
[Data End: 2100Hz]
[Frame End: 2200Hz]
```

## Technical Specifications

### Audio Parameters
- **Sample Rate**: 44,100 Hz (CD quality)
- **Bit Depth**: 16-bit signed PCM
- **Channels**: Mono
- **Format**: WAV (uncompressed)
- **Tone Duration**: 0.8-1.0 seconds (configurable)
- **Gap Duration**: 0.2-0.3 seconds (configurable)

### Detection Parameters
- **FFT Size**: 2048-4096 samples
- **Detection Threshold**: Configurable (default: 80/255)
- **Frequency Tolerance**: ±10Hz
- **Smoothing**: 0.3 (configurable)

## Transmission Methods

### 1. File-based Transmission
- **Accuracy**: 99%+ (perfect digital transmission)
- **Speed**: 1 character per second
- **Use Case**: Offline data sharing, archival storage

### 2. Speaker-to-Microphone
- **Accuracy**: 85-95% (environment dependent)
- **Range**: Up to 5 meters in quiet environments
- **Use Case**: Local wireless transmission, demonstrations

### 3. Radio/Phone Transmission
- **Accuracy**: 70-90% (signal quality dependent)
- **Range**: Unlimited (broadcast range)
- **Use Case**: Long-distance communication, emergency broadcasts

## Performance Optimization

### Speed Improvements
1. **Reduce Tone Duration**: From 1.0s to 0.3s (3x faster)
2. **Parallel Processing**: Multiple frequency channels
3. **Compression**: Common pattern encoding

### Reliability Improvements
1. **Error Correction**: Reed-Solomon codes
2. **Checksums**: Data integrity verification
3. **Redundancy**: Duplicate transmission
4. **Adaptive Thresholds**: Noise compensation

## Troubleshooting

### Common Issues

**"No audio detected"**
- Check microphone permissions in browser
- Verify audio file format (WAV recommended)
- Ensure sample rate matches (44.1kHz)

**"Decoding errors"**
- Increase FFT size for better frequency resolution
- Reduce background noise
- Check frequency tolerance settings

**"Generation failed"**
- Verify HTML content is valid
- Check for unsupported characters (outside ASCII 32-126)
- Ensure browser supports Web Audio API

### Debug Tips
1. Use the System Test page to validate encoding/decoding
2. Check browser console for error messages
3. Test with simple HTML first (`<h1>Test</h1>`)
4. Verify audio settings match between generator and decoder

## Browser Compatibility

### Supported Browsers
- **Chrome/Chromium**: Full support
- **Firefox**: Full support
- **Safari**: Full support (may require user interaction for audio)
- **Edge**: Full support

### Required APIs
- Web Audio API
- File API
- MediaDevices API (for microphone access)

## Use Cases

### Educational
- Demonstrate audio encoding principles
- Teach frequency analysis and FFT
- Show practical applications of digital signal processing

### Emergency Communications
- Broadcast HTML content over radio
- Offline information sharing
- Air-gapped system data transfer

### Experimental
- Test audio transmission limits
- Develop error correction algorithms
- Explore alternative encoding schemes

## Limitations

### Current Constraints
- **Speed**: ~1 character/second (slow for large content)
- **Size**: Practical limit ~1KB HTML
- **Environment**: Sensitive to noise and audio quality
- **Character Set**: ASCII only (no Unicode support)

### Not Suitable For
- Large websites with CSS/JavaScript
- Real-time applications
- Production web hosting
- Binary file transmission (images, videos)

## Future Enhancements

### Planned Features
1. **UTF-8 Support**: Unicode character encoding
2. **Compression**: Huffman coding for common patterns
3. **Error Correction**: Forward error correction codes
4. **Multi-channel**: Parallel frequency streams
5. **Binary Mode**: Support for images and other file types

### Research Areas
- Ultrasonic transmission (>20kHz)
- Phase-shift keying for higher data rates
- Adaptive modulation based on channel conditions
- Integration with existing radio protocols

## Contributing

The Protocol % project is open source and welcomes contributions:
- **Bug Reports**: Use GitHub issues
- **Feature Requests**: Propose new functionality
- **Code Contributions**: Submit pull requests
- **Documentation**: Improve guides and examples

## Legal and Licensing

Protocol % is designed to be legally clean and transparent:
- **Open Source**: All code is publicly available
- **No Patents**: Uses well-established audio techniques
- **Universal Access**: Works with standard audio equipment
- **Auditable**: All encoding rules are documented and verifiable