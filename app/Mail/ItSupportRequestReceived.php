<?php

namespace App\Mail;

use App\Models\ItSupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ItSupportRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ItSupportRequest $supportRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'IT Support Request Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.it-support-request-received',
        );
    }
}
