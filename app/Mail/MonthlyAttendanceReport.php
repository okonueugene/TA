<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class MonthlyAttendanceReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $reportData;
    protected $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct(array $reportData, string $attachmentPath)
    {
        $this->reportData = $reportData;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        // Removed searchFilter condition from subject
        $subject = "Monthly Attendance Report for {$this->reportData['month']} {$this->reportData['year']}";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.monthly-attendance-report',
            with: $this->reportData,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Ensure the file exists before attempting to attach
        if (!file_exists($this->attachmentPath)) {
            \Log::error("Attachment file not found for email: " . $this->attachmentPath);
            return []; // Return empty array if file is missing to prevent mailer errors
        }

        return [
            Attachment::fromPath($this->attachmentPath)
                ->as($this->reportData['fileName'])
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}