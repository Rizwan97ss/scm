<?php

namespace App\Jobs;

use App\Contracts\PushGatewayInterface;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched per recipient by AnnouncementService — see SendSmsJob's
 * docblock for the retry/backoff and tenant-context reasoning, identical
 * here. $recipient is a real model, not a bare id: SerializesModels already
 * re-fetches it safely by key when the job runs, matching this codebase's
 * usual Eloquent-in-job-constructor idiom.
 */
class SendPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly User $recipient,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function handle(PushGatewayInterface $gateway): void
    {
        $gateway->send($this->recipient, $this->title, $this->body);
    }
}
