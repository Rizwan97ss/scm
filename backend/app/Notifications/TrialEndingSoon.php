<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoon extends Notification
{
    use Queueable;

    public function __construct(private readonly School $school) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $endsAt = $this->school->trial_ends_at?->toFormattedDateString() ?? 'soon';

        return (new MailMessage)
            ->subject("Your {$this->school->name} trial ends {$endsAt}")
            ->line("Your free trial of {$this->school->name}'s account ends {$endsAt}.")
            ->line('Add a payment method to keep everything running without interruption.')
            ->action('Manage billing', rtrim(config('app.frontend_url'), '/').'/settings/billing');
    }
}
