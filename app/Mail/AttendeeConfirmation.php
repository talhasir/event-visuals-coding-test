<?php

namespace App\Mail;

use App\Models\Attendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttendeeConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Attendee $attendee)
    {
        $this->attendee->loadMissing('event');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're on the list: ".($this->attendee->event->payload['name'] ?? 'your event'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.attendee-confirmation',
            with: ['card' => $this->attendee->event->toCardArray()],
        );
    }
}
