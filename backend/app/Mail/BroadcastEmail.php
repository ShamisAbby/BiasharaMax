<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BroadcastEmail extends Mailable
{

    public string $mailSubject;
    public string $body;
    public string $recipientName;

    public function __construct(string $mailSubject, string $body, string $recipientName)
    {
        $this->mailSubject    = $mailSubject;
        $this->body          = $body;
        $this->recipientName = $recipientName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.broadcast');
    }
}
