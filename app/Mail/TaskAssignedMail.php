<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public Task $task,
        public string $assigneeName,
        public ?string $actionUrl = null,
        public string $actionLabel = 'View task',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Task assigned: '.$this->task->task_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tasks.assigned',
        );
    }
}
