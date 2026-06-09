<?php

namespace App\Mail;

use App\Models\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Digest $digest) {}

    public function envelope(): Envelope
    {
        $period = $this->digest->period_start->format('F Y');

        return new Envelope(
            subject: "Your {$period} budget digest",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.monthly-digest',
            with: [
                'digest' => $this->digest,
                'recommendations' => $this->digest->payload['recommendations'] ?? [],
                'renewals' => $this->digest->payload['renewals'] ?? [],
            ],
        );
    }
}
