<?php

namespace App\Services;

use App\Contracts\PushGatewayInterface;
use App\Contracts\SmsGatewayInterface;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Composes a broadcast and fans it out to every matching recipient.
 * Deliberately synchronous, not queued — this codebase has no queue worker
 * running yet (see docs/roadmap.md), so dispatching a queued job here would
 * silently never be processed rather than actually sending anything.
 * Queueing this is a reasonable future hardening step once a worker exists.
 */
class AnnouncementService
{
    public function __construct(
        private readonly SmsGatewayInterface $sms,
        private readonly PushGatewayInterface $push,
    ) {}

    /**
     * @param  array{title: string, body: string, audience: string, channels: array<int, string>}  $data
     */
    public function send(array $data, User $sender): Announcement
    {
        $recipients = $this->resolveRecipients(tenant(), $data['audience']);

        $announcement = DB::transaction(function () use ($data, $sender, $recipients) {
            $announcement = Announcement::query()->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'channels' => $data['channels'],
                'recipient_count' => $recipients->count(),
                'sent_by' => $sender->id,
                'sent_at' => now(),
            ]);

            foreach ($recipients as $recipient) {
                $announcement->notifications()->create([
                    'user_id' => $recipient->id,
                    'title' => $data['title'],
                    'body' => $data['body'],
                ]);
            }

            return $announcement;
        });

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
                Mail::to($recipient->email)->send(new AnnouncementMail($announcement, $recipient));
            }

            if (in_array('sms', $channels, true) && $recipient->phone) {
                $this->sms->send($recipient->phone, "{$announcement->title}: {$announcement->body}");
            }

            if (in_array('push', $channels, true)) {
                $this->push->send($recipient, $announcement->title, $announcement->body);
            }
        }
    }
}
