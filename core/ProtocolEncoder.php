<?php

namespace ProtocolPercent\Core;

require_once 'AudioEngine.php';

class ProtocolEncoder {
    private $audioEngine;
    private $frequencies;
    private $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'ultrasonic' => false,
            'tone_duration' => 0.3,
            'gap_duration' => 0.1,
            'fsk_bit_duration' => 0.08,
            'block_size' => 16
        ], $config);
        
        $frequencyShift = $this->config['ultrasonic'] ? 20000 : 0;
        $this->audioEngine = new AudioEngine(44100, 0.7, $frequencyShift);
        $this->initializeFrequencies();
    }
    
    private function initializeFrequencies(): void {
        // Simple, well-separated frequencies for reliable detection
        $this->frequencies = [
            'start_frame' => 800,
            'end_frame' => 900,
            'http_200' => 1000,
            'html_open' => 1100,
            'html_close' => 1200,
            'head_open' => 1300,
            'head_close' => 1400,
            'body_open' => 1500,
            'body_close' => 1600,
            'title' => 1700,
            'h1' => 1800,
            'h2' => 1900,
            'h3' => 2000,
            'p' => 2100,
            'div' => 2200,
            'a' => 2300,
            'text_start' => 2400,
            'text_end' => 2500,
            'fsk_0' => 3000,
            'fsk_1' => 3200
        ];
    }
    
    public function encodeHTML(string $html): array {
        $audio = [];
        
        // Start frame marker
        $audio = array_merge($audio, $this->generateMarker('start_frame'));
        
        // HTTP 200 OK
        $audio = array_merge($audio, $this->generateMarker('http_200'));
        
        // Parse HTML with simple regex (more reliable than DOM for this use case)
        $audio = array_merge($audio, $this->parseAndEncodeHTML($html));
        
        // End frame marker
        $audio = array_merge($audio, $this->generateMarker('end_frame'));
        
        return $audio;
    }
    
    private function parseAndEncodeHTML(string $html): array {
        $audio = [];
        
        // Clean up HTML
        $html = trim($html);
        
        // Encode HTML structure step by step
        if (preg_match('/<html[^>]*>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('html_open'));
        }
        
        if (preg_match('/<head[^>]*>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('head_open'));
        }
        
        // Extract and encode title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $audio = array_merge($audio, $this->generateMarker('title'));
            $audio = array_merge($audio, $this->encodeText($matches[1]));
        }
        
        if (preg_match('/<\/head>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('head_close'));
        }
        
        if (preg_match('/<body[^>]*>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('body_open'));
        }
        
        // Extract and encode H1 tags
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            foreach ($matches[1] as $h1Text) {
                $audio = array_merge($audio, $this->generateMarker('h1'));
                $audio = array_merge($audio, $this->encodeText($h1Text));
            }
        }
        
        // Extract and encode H2 tags
        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches)) {
            foreach ($matches[1] as $h2Text) {
                $audio = array_merge($audio, $this->generateMarker('h2'));
                $audio = array_merge($audio, $this->encodeText($h2Text));
            }
        }
        
        // Extract and encode P tags
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            foreach ($matches[1] as $pText) {
                $audio = array_merge($audio, $this->generateMarker('p'));
                $audio = array_merge($audio, $this->encodeText($pText));
            }
        }
        
        if (preg_match('/<\/body>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('body_close'));
        }
        
        if (preg_match('/<\/html>/i', $html)) {
            $audio = array_merge($audio, $this->generateMarker('html_close'));
        }
        
        return $audio;
    }
    
    private function generateMarker(string $type): array {
        $frequency = $this->frequencies[$type];
        return $this->audioEngine->generateTone(
            $frequency, 
            $this->config['tone_duration'],
            0.02,
            0.02
        );
    }
    
    private function encodeText(string $text): array {
        if (empty(trim($text))) {
            return [];
        }
        
        $audio = [];
        
        // Text start marker
        $audio = array_merge($audio, $this->generateMarker('text_start'));
        
        // Encode text in small blocks
        $blocks = str_split(trim(strip_tags($text)), $this->config['block_size']);
        
        foreach ($blocks as $block) {
            // Block length
            $length = chr(strlen($block));
            $audio = array_merge($audio, $this->audioEngine->encodeFSK(
                $length,
                $this->frequencies['fsk_0'],
                $this->frequencies['fsk_1'],
                $this->config['fsk_bit_duration']
            ));
            
            // Block data
            $audio = array_merge($audio, $this->audioEngine->encodeFSK(
                $block,
                $this->frequencies['fsk_0'],
                $this->frequencies['fsk_1'],
                $this->config['fsk_bit_duration']
            ));
            
            // CRC checksum
            $crc = chr($this->audioEngine->calculateCRC8($block));
            $audio = array_merge($audio, $this->audioEngine->encodeFSK(
                $crc,
                $this->frequencies['fsk_0'],
                $this->frequencies['fsk_1'],
                $this->config['fsk_bit_duration']
            ));
        }
        
        // Text end marker
        $audio = array_merge($audio, $this->generateMarker('text_end'));
        
        return $audio;
    }
    
    public function encodeToFile(string $html, string $filename): bool {
        $audio = $this->encodeHTML($html);
        return $this->audioEngine->saveWAV($audio, $filename);
    }
    
    public function getFrequencyMap(): array {
        return $this->frequencies;
    }
    
    public function getAudioEngine(): AudioEngine {
        return $this->audioEngine;
    }
}