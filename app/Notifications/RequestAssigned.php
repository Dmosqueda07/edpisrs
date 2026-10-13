<?php

namespace App\Notifications;

use App\Models\ItSupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class RequestAssigned extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly ItSupportRequest $supportRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('IT support request assigned')
            ->greeting('An IT support request has been assigned.')
            ->line('Ticket: '.$this->supportRequest->ticket_no)
            ->line('Request: '.$this->supportRequest->summary)
            ->line('Assigned technician: '.$this->supportRequest->technician?->full_name)
            ->action('View request', route('it-support-requests.show', $this->supportRequest));
    }
}
