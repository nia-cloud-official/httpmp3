<?php

require_once 'src/ProtocolEncoder.php';

use ProtocolPercent\ProtocolEncoder;

function showUsage() {
    echo "Protocol % Production Encoder\n";
    echo "Usage: php production_encode.php [options] <input.html> <output.wav>\n\n";
    echo "Options:\n";
    echo "  --ultrasonic     Enable ultrasonic mode (>20kHz)\n";
    echo "  --block-size=N   Set text block size (default: 32)\n";
    echo "  --no-crc         Disable error correction\n";
    echo "  --compress       Enable compression\n";
    echo "  --verbose        Enable verbose output\n";
    echo "  --help           Show this help\n\n";
    echo "Examples:\n";
    echo "  php production_encode.php index.html output.wav\n";
    echo "  php production_encode.php --ultrasonic page.html ultrasonic.wav\n";
    echo "  php production_encode.php --block-size=64 --verbose large.html output.wav\n";
}

function parseArguments($argv) {
    $options = [
        'ultrasonic' => false,
        'block_size' => 32,
        'error_correction' => true,
        'compression' => false,
        'verbose' => false
    ];
    
    $files = [];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--help') {
            showUsage();
            exit(0);
        } elseif ($arg === '--ultrasonic') {
            $options['ultrasonic'] = true;
        } elseif ($arg === '--no-crc') {
            $options['error_correction'] = false;
        } elseif ($arg === '--compress') {
            $options['compression'] = true;
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        } elseif (preg_match('/^--block-size=(\d+)$/', $arg, $matches)) {
            $options['block_size'] = (int)$matches[1];
        } elseif (!str_starts_with($arg, '--')) {
            $files[] = $arg;
        } else {
            echo "Unknown option: $arg\n";
            exit(1);
        }
    }
    
    if (count($files) !== 2) {
        echo "Error: Please specify input HTML file and output WAV file\n\n";
        showUsage();
        exit(1);
    }
    
    return [$options, $files[0], $files[1]];
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    if ($minutes > 0) {
        return sprintf('%dm %.1fs', $minutes, $seconds);
    } else {
        return sprintf('%.1fs', $seconds);
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line\n";
    exit(1);
}

[$options, $inputFile, $outputFile] = parseArguments($argv);

// Validate input file
if (!file_exists($inputFile)) {
    echo "Error: Input file '$inputFile' not found\n";
    exit(1);
}

if (!is_readable($inputFile)) {
    echo "Error: Input file '$inputFile' is not readable\n";
    exit(1);
}

// Check output directory
$outputDir = dirname($outputFile);
if (!is_dir($outputDir)) {
    echo "Error: Output directory '$outputDir' does not exist\n";
    exit(1);
}

if (!is_writable($outputDir)) {
    echo "Error: Output directory '$outputDir' is not writable\n";
    exit(1);
}

try {
    $startTime = microtime(true);
    
    if ($options['verbose']) {
        echo "Protocol % Production Encoder\n";
        echo str_repeat('=', 40) . "\n";
        echo "Input file: $inputFile\n";
        echo "Output file: $outputFile\n";
        echo "Ultrasonic mode: " . ($options['ultrasonic'] ? 'enabled' : 'disabled') . "\n";
        echo "Block size: {$options['block_size']} bytes\n";
        echo "Error correction: " . ($options['error_correction'] ? 'enabled' : 'disabled') . "\n";
        echo "Compression: " . ($options['compression'] ? 'enabled' : 'disabled') . "\n";
        echo "\n";
    }
    
    // Read and validate HTML
    $html = file_get_contents($inputFile);
    if ($html === false) {
        throw new Exception("Failed to read input file");
    }
    
    $htmlSize = strlen($html);
    
    if ($options['verbose']) {
        echo "HTML size: " . formatBytes($htmlSize) . "\n";
        echo "Encoding audio...\n";
    }
    
    // Create encoder
    $encoder = new ProtocolEncoder([
        'mode' => 'production',
        'ultrasonic' => $options['ultrasonic'],
        'block_size' => $options['block_size'],
        'error_correction' => $options['error_correction'],
        'compression' => $options['compression']
    ]);
    
    // Encode to file
    $audioBytes = $encoder->encodeToFile($html, $outputFile);
    
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    $audioDuration = $audioBytes / (44100 * 2); // Approximate audio duration
    
    // Output results
    if ($options['verbose']) {
        echo "Encoding completed!\n\n";
        echo "Results:\n";
        echo "  Audio file: $outputFile\n";
        echo "  File size: " . formatBytes($audioBytes) . "\n";
        echo "  Audio duration: " . formatDuration($audioDuration) . "\n";
        echo "  Encoding time: " . formatDuration($duration) . "\n";
        echo "  Compression ratio: " . number_format($htmlSize / $audioBytes * 100, 2) . "%\n";
        echo "  Data rate: " . number_format($htmlSize / $audioDuration, 1) . " bytes/second\n";
        
        if ($options['ultrasonic']) {
            echo "  Frequency range: 20kHz - 23.2kHz (inaudible)\n";
        } else {
            echo "  Frequency range: 262Hz - 3.2kHz (audible)\n";
        }
        
        echo "\nFrequency Map:\n";
        $frequencies = $encoder->getFrequencyMap();
        foreach (['html_open', 'head_open', 'body_open', 'h1', 'p', 'fsk_0', 'fsk_1'] as $key) {
            if (isset($frequencies[$key])) {
                $freq = is_array($frequencies[$key]) ? implode(', ', $frequencies[$key]) : $frequencies[$key];
                echo "  $key: {$freq}Hz\n";
            }
        }
    } else {
        echo "✓ Encoded $inputFile → $outputFile\n";
        echo "  Size: " . formatBytes($audioBytes) . " | Duration: " . formatDuration($audioDuration) . " | Rate: " . number_format($htmlSize / $audioDuration, 1) . " B/s\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>