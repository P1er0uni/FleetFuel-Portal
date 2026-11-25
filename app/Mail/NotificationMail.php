<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email channel of a notification rule (D10). Tests use the array mailer,
 * so nothing leaves the machine; a real SMTP setup is a deployment step, as
 * for the rest of the portal.
 */
class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $notificationTitle,
        public string $notificationMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationTitle);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            with: [
                'heading' => $this->notificationTitle,
                'body' => $this->notificationMessage,
            ],
        );
    }
}
