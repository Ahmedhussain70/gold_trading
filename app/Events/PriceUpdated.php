<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        
        // Broadcast مباشرة عبر HTTP POST
        $this->broadcastToWebSocket($payload);
    }

    private function broadcastToWebSocket(array $data)
    {
        $appId = config('broadcasting.connections.pusher.app_id');
        $key = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');
        
        $body = json_encode([
            'name' => 'PriceUpdated',
            'channels' => ['gold-price'],
            'data' => json_encode(array_map(function($value) {
                        return is_float($value) ? round($value, 4) : $value;
                    }, $data), JSON_PRESERVE_ZERO_FRACTION)
        ]);
        
        $authTimestamp = time();
        $authVersion = '1.0';
        $bodyMd5 = md5($body);
        
        $authString = "POST\n/apps/{$appId}/events\nauth_key={$key}&auth_timestamp={$authTimestamp}&auth_version={$authVersion}&body_md5={$bodyMd5}";
        $authSignature = hash_hmac('sha256', $authString, $secret);
        
        $url = "http://127.0.0.1:6001/apps/{$appId}/events?auth_key={$key}&auth_timestamp={$authTimestamp}&auth_version={$authVersion}&body_md5={$bodyMd5}&auth_signature={$authSignature}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        
        curl_exec($ch);
        curl_close($ch);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('gold-price');
    }

    public function broadcastAs(): string
    {
        return 'PriceUpdated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
