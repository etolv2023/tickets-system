<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\Ticket;
use App\Support\Locale;
use App\Support\Phrase;
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
        public readonly string|Phrase $message,
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
            // Label and group are derived from `event` when shown, in the
            // reader's language; these copies serve rows read by older code.
            'group' => $this->event->groupKey(),
            'icon' => $this->event->icon(),
            'variant' => $this->event->variant(),
            'label' => Locale::as('ar', fn () => $this->event->label()),
            'message' => $this->arabicText(),
            'message_key' => $this->message instanceof Phrase ? $this->message->key : null,
            'message_params' => $this->message instanceof Phrase ? $this->message->params : null,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'ticket_title' => $this->ticket->title,
            'subtask_id' => $this->subtaskId,
            'actor_id' => $this->actorId,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // No per-person language: email goes out in the system's default one.
        return Locale::as(config('app.locale'), fn () => $this->buildMail($notifiable));
    }

    private function buildMail(object $notifiable): MailMessage
    {
        $text = $this->message instanceof Phrase ? $this->message->render() : $this->message;

        $mail = (new MailMessage())
            ->subject("[{$this->ticket->ticket_number}] {$this->event->label()} — {$this->ticket->title}")
            ->greeting(__('مرحبًا :name', ['name' => $notifiable->name]))
            ->line($text)
            ->action(__('افتح التذكرة'), route('tickets.show', $this->ticket))
            ->salutation(__('مع التحية'));

        // The from address is a setting, not a config constant: whoever runs
        // this changes it without a deploy.
        $from = Setting::get('mail_from_address');

        if (filled($from)) {
            $mail->from($from, Setting::get('mail_from_name') ?: Setting::get('app_name'));
        }

        return $mail;
    }

    /** The sentence saved with the row: what any reader without phrase support shows. */
    private function arabicText(): string
    {
        return $this->message instanceof Phrase ? $this->message->render('ar') : $this->message;
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
