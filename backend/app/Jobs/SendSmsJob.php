<?php

namespace App\Jobs;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched per recipient by AnnouncementService — a transient network call
 * to a third-party provider, so (unlike GenerateDataExportJob's tries=1) this
 * retries a few times with backoff before landing in failed_jobs. Tenant
 * context is restored automatically by stancl's QueueTenancyBootstrapper
 * before handle() runs (config/tenancy.php), same as every other queued job
 * dispatched from inside a tenant request.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
    ) {}

    public function handle(SmsGatewayInterface $gateway): void
    {
        $gateway->send($this->phone, $this->message);
    }
}
