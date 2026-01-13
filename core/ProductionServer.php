<?php

namespace ProtocolPercent\Core;

require_once 'ProtocolEncoder.php';

class ProductionServer {
    private $host;
    private $port;
    private $documentRoot;
    private $encoder;
    private $socket;
    
    public function __construct(string $host = '0.0.0.0', int $port = 8080, string $documentRoot = '.') {
        $this->host = $host;
        $this->port = $port;
        $this->documentRoot = realpath($documentRoot);
        $this->encoder = new ProtocolEncoder();
    }
    
    public function start(): void {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$this->socket) {
            throw new \RuntimeException('Failed to create socket');
        }
        
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new \RuntimeException("Failed to bind to {$this->host}:{$this->port}");
        }
        
        if (!socket_listen($this->socket, 50)) {
            throw new \RuntimeException('Failed to listen on socket');
        }
        
        $this->log("Protocol % Server started on {$this->host}:{$this->port}");
        $this->log("Document root: {$this->documentRoot}");
        
        while (true) {
            $client = socket_accept($this->socket);
            if ($client) {
                $this->handleClient($client);
                socket_close($client);
            }
        }
    }
    
    private function handleClient($client): void {
        $request = socket_read($client, 8192);
        if (!$request) return;
        
        $lines = explode("\r\n", $request);
        $requestLine = $lines[0];
        
        if (!preg_match('/^(GET|HEAD)\s+(\S+)/', $requestLine, $matches)) {
            $this->sendError($client, 400, 'Bad Request');
            return;
        }
        
        $method = $matches[1];
        $path = parse_url($matches[2], PHP_URL_PATH);
        
        $this->log("$method $path");
        
        $this->routeRequest($client, $method, $path);
    }
    
    private function routeRequest($client, string $method, string $path): void {
        // API endpoints
        if ($path === '/api/frequencies') {
            $this->sendJSON($client, [
                'frequencies' => $this->encoder->getFrequencyMap(),
                'sample_rate' => 44100,
                'ultrasonic_mode' => false
            ]);
            return;
        }
        
        // Audio generation
        if (preg_match('/^\/audio\/(.+\.html)$/', $path, $matches)) {
            $this->serveAudio($client, $matches[1]);
            return;
        }
        
        // Static files
        if ($path === '/' || $path === '/index.html') {
            $this->serveFile($client, 'final_client.html');
            return;
        }
        
        if (preg_match('/^\/(.+\.(html|css|js|wav|json))$/', $path, $matches)) {
            $this->serveFile($client, $matches[1]);
            return;
        }
        
        $this->sendError($client, 404, 'Not Found');
    }
    
    private function serveAudio($client, string $filename): void {
        $filepath = $this->documentRoot . '/' . $filename;
        
        if (!file_exists($filepath)) {
            $this->sendError($client, 404, 'File not found');
            return;
        }
        
        try {
            $html = file_get_contents($filepath);
            $audio = $this->encoder->encodeHTML($html);
            $pcmData = $this->encoder->getAudioEngine()->toPCM16($audio);
            $wavData = $this->encoder->getAudioEngine()->createWAVHeader(strlen($pcmData)) . $pcmData;
            
            $this->sendHeaders($client, [
                'Content-Type' => 'audio/wav',
                'Content-Length' => strlen($wavData),
                'Content-Disposition' => 'inline; filename="' . basename($filename, '.html') . '.wav"'
            ]);
            
            socket_write($client, $wavData);
            
            $this->log("Served audio: $filename (" . number_format(strlen($wavData)) . " bytes)");
            
        } catch (\Exception $e) {
            $this->log("Error encoding $filename: " . $e->getMessage());
            $this->sendError($client, 500, 'Encoding error');
        }
    }
    
    private function serveFile($client, string $filename): void {
        $filepath = $this->documentRoot . '/' . $filename;
        
        if (!file_exists($filepath)) {
            $this->sendError($client, 404, 'File not found');
            return;
        }
        
        $content = file_get_contents($filepath);
        $contentType = $this->getContentType($filename);
        
        $this->sendHeaders($client, [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($content)
        ]);
        
        socket_write($client, $content);
    }
    
    private function sendJSON($client, array $data): void {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        $this->sendHeaders($client, [
            'Content-Type' => 'application/json',
            'Content-Length' => strlen($json)
        ]);
        
        socket_write($client, $json);
    }
    
    private function sendError($client, int $code, string $message): void {
        $this->sendHeaders($client, [
            'Content-Type' => 'text/plain',
            'Content-Length' => strlen($message)
        ], $code);
        
        socket_write($client, $message);
    }
    
    private function sendHeaders($client, array $headers, int $code = 200): void {
        $statusText = [
            200 => 'OK',
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        ][$code] ?? 'Unknown';
        
        $response = "HTTP/1.1 $code $statusText\r\n";
        
        $defaultHeaders = [
            'Server' => 'Protocol% Production/1.0',
            'Date' => gmdate('D, d M Y H:i:s T'),
            'Connection' => 'close',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type'
        ];
        
        foreach (array_merge($defaultHeaders, $headers) as $name => $value) {
            $response .= "$name: $value\r\n";
        }
        
        $response .= "\r\n";
        socket_write($client, $response);
    }
    
    private function getContentType(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'wav' => 'audio/wav',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif'
        ][$ext] ?? 'application/octet-stream';
    }
    
    private function log(string $message): void {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
    
    public function stop(): void {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
}