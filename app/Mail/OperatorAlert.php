<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OperatorAlert extends Mailable
{
    public function __construct(
        public readonly string $alertKey,
        public readonly string $alertMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Alerta] '.$this->alertKey);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.operator-alert');
    }
}
