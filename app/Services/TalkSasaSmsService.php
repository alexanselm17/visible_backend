<?php

namespace App\Services;

use App\Exceptions\SmsDeliveryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TalkSasaSmsService
{
    public function send(string $phone, string $message): ?string
    {
        $apiKey = trim((string) config('services.talksasa.api_key'));
        $senderId = trim((string) config('services.talksasa.sender_id', 'VisibleDM'));
        $baseUrl = rtrim((string) config('services.talksasa.base_url', 'https://bulksms.talksasa.com/api/v3'), '/');
        $endpoint = '/' . ltrim((string) config('services.talksasa.send_path', 'sms/send'), '/');

        if ($apiKey === '') {
            throw new SmsDeliveryException('TalkSasa API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('services.talksasa.timeout', 30))
            ->post($baseUrl . $endpoint, [
                'recipient' => $this->normalizePhone($phone),
                'sender_id' => $senderId,
                'type' => 'plain',
                'message' => $message,
            ]);

        if (! $response->successful()) {
            Log::error('TalkSasa SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new SmsDeliveryException('TalkSasa could not send the SMS.');
        }

        return (string) (
            data_get($response->json(), 'message_id')
            ?: data_get($response->json(), 'data.id')
            ?: data_get($response->json(), 'id')
            ?: ''
        ) ?: null;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/^\+/', '', trim($phone));
    }
}
