<?php

namespace App\Modules\Alert\Notifications;

use App\Modules\Alert\Models\Alert;
use App\Modules\Alert\Support\SpeedConverter;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Alert $alert,
        public readonly Vehicle $vehicle,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $speedKmh = SpeedConverter::knotsToKmh($this->alert->speed);
        $occurred = $this->alert->occurred_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');

        $mail = (new MailMessage)
            ->subject('[ALERTA] '.$this->alert->title)
            ->greeting('Alerta de rastreamento')
            ->line('Tipo: '.$this->alert->type->label())
            ->line('Veículo: '.$this->vehicle->plate)
            ->line('Severidade: '.strtoupper($this->alert->severity->value))
            ->line('Data: '.($occurred ?? '—'));

        if ($speedKmh !== null) {
            $limit = $this->alert->meta['limit_kmh'] ?? null;
            $mail->line(sprintf('Velocidade: %.0f km/h', $speedKmh));
            if ($limit !== null) {
                $mail->line(sprintf('Limite: %.0f km/h', (float) $limit));
            }
        }

        if ($this->alert->latitude !== null && $this->alert->longitude !== null) {
            $mail->line(sprintf(
                'Localização: %.6f, %.6f',
                $this->alert->latitude,
                $this->alert->longitude,
            ));
        }

        if (filled($this->alert->description)) {
            $mail->line($this->alert->description);
        }

        $url = rtrim((string) config('app.frontend_url'), '/').'/alerts';

        return $mail->action('Ver alertas', $url);
    }
}
