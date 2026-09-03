<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Jobs\SendPushJob;
use App\Jobs\SendSmsJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Composes a broadcast and fans it out to every matching recipient. Each
 * channel is queued per recipient (Mail::queue()/SendSmsJob/SendPushJob),
 * not sent inline — a worker has run in production since Phase 15
 * (GenerateDataExportJob), it just had nothing else to process until now.
 */
class AnnouncementService
{
    /**
     * @param  array{title: string, body: string, audience: string, channels: array<int, string>}  $data
     */
    public function send(array $data, User $sender): Announcement
    {
        $school = tenant();
        $recipients = $this->resolveRecipients($school, $data['audience']);

        [$announcement, $notifications] = DB::transaction(function () use ($data, $sender, $recipients) {
            $announcement = Announcement::query()->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'channels' => $data['channels'],
                'recipient_count' => $recipients->count(),
                'sent_by' => $sender->id,
                'sent_at' => now(),
            ]);

            $notifications = $recipients->map(fn (User $recipient) => $announcement->notifications()->create([
                'user_id' => $recipient->id,
                'title' => $data['title'],
                'body' => $data['body'],
            ]));

            return [$announcement, $notifications];
        });

        // Broadcast only after the transaction actually commits -- this
        // fires synchronously (ShouldBroadcastNow, no queue worker exists
        // to defer it to), so broadcasting from inside the transaction
        // could tell a browser about a notification a rollback then erases.
        foreach ($notifications as $notification) {
            event(new NotificationCreated($notification, $school->id));
        }

        $this->dispatchChannels($announcement, $recipients, $data['channels']);

        return $announcement;
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(School $school, string $audience): Collection
    {
        $query = User::query();

        $query = match ($audience) {
            'students' => $query->whereHas('roles', fn ($q) => $q->where('name', 'Student')),
            'staff' => $query->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['Student', 'Parent'])),
            'parents' => $query->whereHas('roles', fn ($q) => $q->where('name', 'Parent')),
            default => $query,
        };

        return $query->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<int, string>  $channels
     */
    private function dispatchChannels(Announcement $announcement, Collection $recipients, array $channels): void
    {
        foreach ($recipients as $recipient) {
            if (in_array('email', $channels, true) && $recipient->email) {
                Mail::to($recipient->email)->queue(new AnnouncementMail($announcement, $recipient));
            }

            if (in_array('sms', $channels, true) && $recipient->phone) {
                SendSmsJob::dispatch($recipient->phone, "{$announcement->title}: {$announcement->body}");
            }

            if (in_array('push', $channels, true)) {
                SendPushJob::dispatch($recipient, $announcement->title, $announcement->body);
            }
        }
    }
}
