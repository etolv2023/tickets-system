<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for every ticket event (F20). The database channel is
 * always on; email is opt-in from settings and off by default (F22.2).
 */
class TicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $event,
        public readonly string $message,
        public readonly ?int $actorId = null,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (Setting::get('email_notifications_enabled', false) && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'message' => $this->message,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'ticket_title' => $this->ticket->title,
            'actor_id' => $this->actorId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("[{$this->ticket->ticket_number}] {$this->ticket->title}")
            ->greeting("أهلاً {$notifiable->name}")
            ->line($this->message)
            ->action('افتح التذكرة', route('tickets.show', $this->ticket))
            ->salutation('تحياتنا');
    }
}
