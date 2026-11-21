<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestMessage extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' test email');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test email from '.e(config('app.name')).'. If you can read it, sending works.</p>');
    }
}
