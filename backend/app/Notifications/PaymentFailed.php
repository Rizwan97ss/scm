<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly School $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment failed for {$this->school->name}")
            ->line("We couldn't process your latest payment for {$this->school->name}'s account.")
            ->line('Stripe will retry automatically, but you have read-only access in the meantime if all retries fail.')
            ->action('Update payment method', rtrim(config('app.frontend_url'), '/').'/settings/billing');
    }
}
