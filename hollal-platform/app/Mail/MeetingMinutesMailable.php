<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Services\MeetingMinutesPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingMinutesMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'محضر اجتماع: '.$this->meeting->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.meeting-minutes-plain',
            with: [
                'meetingTitle' => $this->meeting->title,
                'scheduledAt' => $this->meeting->scheduled_at?->format('Y-m-d H:i'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = app(MeetingMinutesPdfService::class)->output($this->meeting);

        return [
            Attachment::fromData(fn () => $pdf, 'meeting-minutes-'.$this->meeting->id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
