<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserRegistration extends Notification implements ShouldQueue
{
    use Queueable;
    protected $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // dd($this->user);
        $token = $this->user->token;

        return (new MailMessage)
            ->greeting('Hello!')
            ->line('Thanks for registering with Ojet.')
            ->line('Click on the button to verify your account')
            ->action('Verify Now', url("/api/verify/{$token}"))
            ->line('Thank you for using Ojet!');
    }

    /**
     * Get the nexmo representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toNexmo($notifiable)
    {
        $token = $this->user->token;

        return (new NexmoMessage)
            ->content('Your Tutornia code is')
            ->content($token)
            ->content('Thank you for using Tutornia!')
            ->unicode();
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'user' => $this->user,
            'token' => $this->user->token,
        ];
    }
}