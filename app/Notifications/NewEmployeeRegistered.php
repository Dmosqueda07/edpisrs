<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class NewEmployeeRegistered extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly User $registeredUser) {}

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
            ->subject('Employee registration awaiting approval')
            ->greeting('A new employee has registered.')
            ->line('Name: '.$this->registeredUser->full_name)
            ->line('Email: '.$this->registeredUser->email)
            ->line('Division: '.$this->registeredUser->division->value)
            ->action('Review user accounts', route('users.index'));
    }
}
