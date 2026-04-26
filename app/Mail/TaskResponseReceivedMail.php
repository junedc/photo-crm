<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskResponseReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Booking $booking,
        public Task $task,
        public ?string $note = null,
        public array $responseAttachments = [],
        public ?string $actionUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Customer task update: '.$this->task->task_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tasks.response-received',
        );
    }
}
