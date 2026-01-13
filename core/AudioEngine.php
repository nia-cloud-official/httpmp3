<?php

namespace ProtocolPercent\Core;

class AudioEngine {
    private $sampleRate;
    private $amplitude;
    private $frequencyShift;
    
    public function __construct(int $sampleRate = 44100, float $amplitude = 0.8, int $frequencyShift = 0) {
        $this->sampleRate = $sampleRate;
        $this->amplitude = $amplitude;
        $this->frequencyShift = $frequencyShift;
    }
    
    public function generateTone(float $frequency, float $duration, float $fadeIn = 0.01, float $fadeOut = 0.01): array {
        $frequency += $this->frequencyShift;
        $samples = (int)($duration * $this->sampleRate);
        $fadeInSamples = (int)($fadeIn * $this->sampleRate);
        $fadeOutSamples = (int)($fadeOut * $this->sampleRate);
        
        $audio = [];
        for ($i = 0; $i < $samples; $i++) {
            $t = $i / $this->sampleRate;
            $sample = sin(2 * M_PI * $frequency * $t) * $this->amplitude;
            
            // Apply fade envelope
            if ($i < $fadeInSamples) {
                $sample *= ($i / $fadeInSamples);
            }
            if ($i >= $samples - $fadeOutSamples) {
                $sample *= (($samples - $i) / $fadeOutSamples);
            }
            
            $audio[] = $sample;
        }
        
        return $audio;
    }
    
    public function generateSequence(array $frequencies, float $toneDuration, float $gap = 0.05): array {
        $audio = [];
        
        foreach ($frequencies as $freq) {
            $tone = $this->generateTone($freq, $toneDuration);
            $audio = array_merge($audio, $tone);
            
            if ($gap > 0) {
                $silence = array_fill(0, (int)($gap * $this->sampleRate), 0.0);
                $audio = array_merge($audio, $silence);
            }
        }
        
        return $audio;
    }
    
    public function encodeFSK(string $data, float $freq0, float $freq1, float $bitDuration = 0.1): array {
        $audio = [];
        
        foreach (str_split($data) as $char) {
            $ascii = ord($char);
            
            // Start bit
            $audio = array_merge($audio, $this->generateTone($freq1, $bitDuration, 0, 0));
            
            // Data bits (LSB first)
            for ($bit = 0; $bit < 8; $bit++) {
                $bitValue = ($ascii >> $bit) & 1;
                $frequency = $bitValue ? $freq1 : $freq0;
                $audio = array_merge($audio, $this->generateTone($frequency, $bitDuration, 0, 0));
            }
            
            // Stop bit
            $audio = array_merge($audio, $this->generateTone($freq0, $bitDuration, 0, 0));
        }
        
        return $audio;
    }
    
    public function toPCM16(array $audio): string {
        $pcm = '';
        foreach ($audio as $sample) {
            $sample = max(-1.0, min(1.0, $sample));
            $intSample = (int)($sample * 32767);
            $pcm .= pack('s', $intSample);
        }
        return $pcm;
    }
    
    public function createWAVHeader(int $dataSize): string {
        return 'RIFF' . pack('V', $dataSize + 36) . 'WAVE' .
               'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1) .
               pack('V', $this->sampleRate) . pack('V', $this->sampleRate * 2) .
               pack('v', 2) . pack('v', 16) .
               'data' . pack('V', $dataSize);
    }
    
    public function saveWAV(array $audio, string $filename): bool {
        $pcmData = $this->toPCM16($audio);
        $header = $this->createWAVHeader(strlen($pcmData));
        return file_put_contents($filename, $header . $pcmData) !== false;
    }
    
    public function calculateCRC8(string $data): int {
        $crc = 0;
        foreach (str_split($data) as $byte) {
            $crc ^= ord($byte);
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x80) ? (($crc << 1) ^ 0x07) : ($crc << 1);
                $crc &= 0xFF;
            }
        }
        return $crc;
    }
}