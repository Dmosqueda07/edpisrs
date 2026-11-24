<?php

namespace App\Notifications;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class RequestClaimed extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly SupportRequest $supportRequest) {}

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
            ->subject('Your IT support request is being handled: '.$this->supportRequest->ticket_no)
            ->greeting('Your IT support request has been claimed.')
            ->line('Ticket: '.$this->supportRequest->ticket_no)
            ->line(($this->supportRequest->technician?->full_name ?? 'An EDP technician').' is handling your request.')
            ->action('View request', route('it-support-requests.show', $this->supportRequest));
    }
}
