<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AchatHubTransactionalNotification extends Notification
{
    use Queueable;

    /**
     * @param list<string> $lines
     */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject)
            ->greeting($this->greeting);

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->actionText && $this->actionUrl) {
            $message->action($this->actionText, $this->actionUrl);
        }

        return $message
            ->line('Merci de votre confiance.')
            ->salutation('L’équipe AchatHub');
    }
}
