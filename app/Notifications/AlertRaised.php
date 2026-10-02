<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertRaised extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['mail'];

        if ($notifiable->phone && config('nexus.whatsapp.driver')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[Nexus] '.$this->alert->title)
            ->greeting($this->alert->type->label())
            ->line($this->alert->message);

        if ($this->alert->severity === 'critical') {
            $mail->error();
        }

        return $mail
            ->action('Ver en Nexus', route('alerts.index'))
            ->line('Fecha: '.$this->alert->created_at->timezone('America/Bogota')->format('d/m/Y h:i a'));
    }

    public function toWhatsApp(User $notifiable): string
    {
        $icon = match ($this->alert->severity) {
            'critical' => '🔴',
            'warning' => '🟠',
            default => '🟢',
        };

        return "{$icon} *Nexus · {$this->alert->title}*\n{$this->alert->message}\n"
            .$this->alert->created_at->timezone('America/Bogota')->format('d/m/Y h:i a');
    }
}
