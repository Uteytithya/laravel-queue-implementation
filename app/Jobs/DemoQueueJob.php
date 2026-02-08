<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DemoQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $requestId, public string $message)
    {
    }

    public function handle(): void
    {
        $processingSeconds = max(1, (int) env('QUEUE_DEMO_PROCESSING_SECONDS', 60));

        DB::table('queue_requests')
            ->where('id', $this->requestId)
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        Log::info('DemoQueueJob started', [
            'request_id' => $this->requestId,
            'message' => $this->message,
            'job_id' => $this->job?->getJobId(),
            'worker' => gethostname(),
            'processing_seconds' => $processingSeconds,
            'started_at' => now()->toDateTimeString(),
        ]);

        sleep($processingSeconds);

        Log::info('DemoQueueJob processed', [
            'request_id' => $this->requestId,
            'message' => $this->message,
            'job_id' => $this->job?->getJobId(),
            'worker' => gethostname(),
            'processed_at' => now()->toDateTimeString(),
        ]);

        DB::table('queue_requests')
            ->where('id', $this->requestId)
            ->update([
                'status' => 'completed',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function failed(Throwable $exception): void
    {
        DB::table('queue_requests')
            ->where('id', $this->requestId)
            ->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
