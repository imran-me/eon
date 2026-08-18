<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = [
    'cluster' => 'ap2', // Check your cluster in Pusher dashboard
    'useTLS' => true
];
$pusher = new Pusher\Pusher(
    config('broadcasting.connections.pusher.key'), // Your Pusher app key
    config('broadcasting.connections.pusher.secret'), // Your Pusher app secret
    config('broadcasting.connections.pusher.app_id'), // Your Pusher app ID
    $options
);

try {
    $pusher->trigger('my-channel', 'my-event', ['message' => 'hello world']);
    echo "Pusher sent successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}