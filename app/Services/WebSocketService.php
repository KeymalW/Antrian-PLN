<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WebSocketService
{
    protected string $serverUrl;
    protected string $secret;

    public function __construct()
    {
        $this->serverUrl = env('WS_SERVER_URL', 'http://127.0.0.1:3002');
        $this->secret = env('WS_BROADCAST_SECRET', '');
    }

    public function broadcast(string $type, $payload): void
    {
        try {
            Http::timeout(1)->withHeaders([
                'X-Broadcast-Secret' => $this->secret,
            ])->post("{$this->serverUrl}/broadcast", [
                'type' => $type,
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            // silently fail — broadcasting is non-critical
        }
    }
}
