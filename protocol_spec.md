# Protocol %: Sound Server Specification

## Core Principles

1. **Universal Medium**: Sound waves as the transport layer
2. **Auditability**: All encoding rules are explicit and reversible
3. **No Conventional Stacks**: Sound replaces HTTP/TCP infrastructure
4. **Deterministic**: Same input always produces identical output
5. **Parameterized**: All frequencies and timings are configurable

## Audio Frame Structure

### Frame Format
```
[START_MARKER] [RESPONSE_CODE] [CONTENT_LENGTH] [PAYLOAD] [CRC] [END_MARKER]
```

### Timing
- Symbol duration: 100ms (10 symbols/second)
- Marker duration: 2000ms
- Silence gap: 50ms between elements

## Frequency Allocation

### Base Frequencies (Musical Mode)
```
Structural Elements:
- START_MARKER: 440Hz + 554Hz (perfect fourth chord)
- END_MARKER: 440Hz + 330Hz (perfect fifth chord)
- <html>: 440Hz sustained
- <head>: 660Hz sustained  
- <body>: 880Hz sustained
- <title>: Melodic sequence 523→659→784Hz
- <h1>: 1046Hz (high amplitude)
- <h2>: 880Hz (medium amplitude)
- <p>: 440Hz base with FSK modulation
- <a>: Transition 392→494Hz

Text Encoding (FSK):
- Binary 0: 1000Hz
- Binary 1: 1200Hz
- Sync bit: 1500Hz

Response Codes:
- 200 OK: 523→659→784Hz (ascending major)
- 404: 784→659→523Hz (descending)
- 500: 1000→200Hz sweep
- Timeout: Silence (3000ms)
```

### Ultrasonic Mode (+20kHz shift)
All base frequencies shifted up by 20000Hz for inaudible operation.

## Text Encoding Protocol

### Character Encoding
1. Convert ASCII character to 8-bit binary
2. Add start bit (1) and stop bit (0): `1[8-bits]0`
3. Encode each bit as FSK tone (100ms duration)
4. Add CRC-8 checksum every 16 characters

### Block Structure
```
[SYNC_PATTERN] [LENGTH] [DATA_BYTES] [CRC8] [SYNC_PATTERN]
```

### Error Handling
- CRC mismatch: Request retransmission with 1500Hz tone
- Timeout: Sender repeats last block after 2-second pause
- Maximum 3 retransmission attempts

## HTML Element Mapping

### Document Structure
```html
<html>     → START_MARKER + 440Hz sustained (500ms)
<head>     → 660Hz sustained (300ms)
<title>    → Melodic phrase + FSK text content
</head>    → 660Hz fade (200ms)
<body>     → 880Hz sustained (300ms)
<h1>       → 1046Hz burst + FSK text content
<p>        → 440Hz base + FSK text content
<a href>   → 392→494Hz + FSK URL + FSK link text
</body>    → 880Hz fade (200ms)
</html>    → END_MARKER
```

### Attributes
- `href` URLs: Encoded as FSK after element marker
- `class`, `id`: Optional, encoded as key=value pairs
- Text content: Always FSK-encoded after element marker

## Implementation Requirements

### PHP Server
- Generate PCM audio buffers directly
- Support WAV file output with proper headers
- Configurable parameters via command line or config file
- Real-time streaming capability

### JavaScript Client
- Web Audio API for capture and playback
- Real-time FFT for spectrogram visualization
- Incremental HTML reconstruction
- Visual feedback for decoding progress

### Audio Quality
- Sample rate: 44100Hz
- Bit depth: 16-bit signed PCM
- Channels: Mono
- No compression artifacts

## Testing Protocol

### Validation Steps
1. Encode known HTML → Verify audio structure
2. Decode generated audio → Compare with original HTML
3. Round-trip test: HTML → Audio → HTML (must be identical)
4. Error injection: Verify CRC detection and recovery
5. Ultrasonic test: Confirm inaudible operation

### Performance Metrics
- Encoding speed: Target 1KB HTML/second
- Decoding accuracy: >99.9% with error correction
- Latency: <500ms for small documents
- Audio quality: No audible artifacts in musical mode