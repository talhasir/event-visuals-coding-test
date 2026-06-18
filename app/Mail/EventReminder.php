<?php

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Attendee  $attendee  the registered attendee
     * @param  string  $window  human label for the reminder window ("3 days" / "24 hours")
     */
    public function __construct(public Attendee $attendee, public string $window)
    {
        $this->attendee->loadMissing('event');
    }

    public function envelope(): Envelope
    {
        $name = $this->attendee->event->payload['name'] ?? 'your event';

        return new Envelope(
            subject: "Reminder: {$name} is in {$this->window}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.event-reminder',
            with: [
                'card' => $this->attendee->event->toCardArray(),
                'window' => $this->window,
            ],
        );
    }
}
