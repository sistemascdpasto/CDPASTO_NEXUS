<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de WhatsApp vía CallMeBot (uso personal, gratis) o la API Cloud de Meta.
 *
 * CallMeBot: el campo "teléfono" del usuario se guarda como "573001234567|APIKEY"
 * (cada destinatario activa su propia API key escribiéndole al bot).
 */
class WhatsAppChannel
{
    public function send(User $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp') || ! $notifiable->phone) {
            return;
        }

        $text = $notification->toWhatsApp($notifiable);

        $response = match (config('nexus.whatsapp.driver')) {
            'callmebot' => $this->viaCallMeBot($notifiable->phone, $text),
            'meta' => $this->viaMeta($notifiable->phone, $text),
            default => null,
        };

        if ($response && $response->failed()) {
            Log::warning('Nexus: fallo al enviar WhatsApp', [
                'user' => $notifiable->id,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);
        }
    }

    private function viaCallMeBot(string $phone, string $text): ?Response
    {
        [$number, $apiKey] = array_pad(explode('|', $phone, 2), 2, null);

        if (! $apiKey) {
            return null;
        }

        return Http::timeout(15)->get(config('nexus.whatsapp.callmebot_url'), [
            'phone' => preg_replace('/\D/', '', $number),
            'text' => $text,
            'apikey' => trim($apiKey),
        ]);
    }

    private function viaMeta(string $phone, string $text): Response
    {
        $number = preg_replace('/\D/', '', explode('|', $phone)[0]);

        return Http::timeout(15)
            ->withToken(config('nexus.whatsapp.meta_token'))
            ->post('https://graph.facebook.com/v21.0/'.config('nexus.whatsapp.meta_phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $number,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);
    }
}
