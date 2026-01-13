<?php

require_once 'src/SoundServer.php';

use ProtocolPercent\SoundServer;

// Production configuration
$config = [
    'host' => '0.0.0.0',
    'port' => 8080,
    'document_root' => __DIR__,
    'max_connections' => 200,
    'timeout' => 60,
    'buffer_size' => 16384,
    'enable_cors' => true,
    'enable_compression' => true,
    'ultrasonic_mode' => false
];

// Override with environment variables
if (getenv('PROTOCOL_HOST')) {
    $config['host'] = getenv('PROTOCOL_HOST');
}

if (getenv('PROTOCOL_PORT')) {
    $config['port'] = (int)getenv('PROTOCOL_PORT');
}

if (getenv('PROTOCOL_ULTRASONIC')) {
    $config['ultrasonic_mode'] = filter_var(getenv('PROTOCOL_ULTRASONIC'), FILTER_VALIDATE_BOOLEAN);
}

// Signal handlers for graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$server) {
        if ($server) {
            $server->stop();
        }
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() use (&$server) {
        if ($server) {
            $server->stop();
        }
        exit(0);
    });
}

try {
    $server = new SoundServer($config);
    $server->start();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

?>