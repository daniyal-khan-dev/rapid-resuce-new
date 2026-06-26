<?php

namespace App\Mail;

use App\Models\EmergencyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RideCompletionMail extends Mailable
{
    use Queueable, SerializesModels;

    public EmergencyRequest $emergencyRequest;

    public function __construct(EmergencyRequest $emergencyRequest)
    {
        $this->emergencyRequest = $emergencyRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ride Completed — ' . $this->emergencyRequest->rreb_id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ride_completion',
        );
    }
}
