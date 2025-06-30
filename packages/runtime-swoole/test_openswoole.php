<?php

require_once __DIR__ . '/vendor/autoload.php';

use OpenSwoole\HTTP\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

// 1. Check if the extension is loaded
if (!extension_loaded('openswoole')) {
    echo "Error: The 'openswoole' extension is not loaded." . PHP_EOL;
    exit(1);
}

echo "✅ OpenSwoole extension is loaded." . PHP_EOL;

// 2. Create a simple HTTP server
$host = '127.0.0.1';
$port = 9501;

try {
    $server = new Server($host, $port);
} catch (Throwable $e) {
    echo "Error: Failed to create server. Is port {$port} already in use?" . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}


$server->on("Start", function (Server $server) use ($host, $port) {
    echo "🚀 OpenSwoole HTTP server is started at http://{$host}:{$port}" . PHP_EOL;
    echo "ℹ️ Press Ctrl+C to stop the server." . PHP_EOL;
});

$server->on("Request", function (Request $request, Response $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello, OpenSwoole!");
});

echo "Starting server..." . PHP_EOL;
$server->start();
