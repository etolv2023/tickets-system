<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification type for every ticket event (F20).
 *
 * The database channel is always on. Email is opt-in from settings, off by
 * default, and even when on it only carries the events worth interrupting
 * someone for — see NotificationEvent::emailWorthy(). Mailing every event is
 * how an address ends up filtered into a folder nobody opens.
 */
class TicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly NotificationEvent $event,
        public readonly string $message,
        public readonly ?int $actorId = null,
        public readonly ?int $subtaskId = null,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event->value,
            'group' => $this->event->group(),
            'icon' => $this->event->icon(),
            'variant' => $this->event->variant(),
            'label' => $this->event->label(),
            'message' => $this->message,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'ticket_title' => $this->ticket->title,
            'subtask_id' => $this->subtaskId,
            'actor_id' => $this->actorId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject("[{$this->ticket->ticket_number}] {$this->event->label()} — {$this->ticket->title}")
            ->greeting("أهلاً {$notifiable->name}")
            ->line($this->message)
            ->action('افتح التذكرة', route('tickets.show', $this->ticket))
            ->salutation('تحياتنا');

        // The from address is a setting, not a config constant: whoever runs
        // this changes it without a deploy.
        $from = Setting::get('mail_from_address');

        if (filled($from)) {
            $mail->from($from, Setting::get('mail_from_name') ?: Setting::get('app_name'));
        }

        return $mail;
    }

    private function shouldEmail(object $notifiable): bool
    {
        return Setting::get('email_notifications_enabled', false)
            && $this->event->emailWorthy()
            && filled($notifiable->email)
            // A person can switch their own email off without switching it off
            // for the whole team.
            && ($notifiable->email_notifications ?? true);
    }
}
