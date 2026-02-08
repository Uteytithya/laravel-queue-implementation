<?php

namespace Tests\Feature;

use App\Jobs\DemoQueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueDemoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_one_endpoint_queues_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/queue/demo', [
            'message' => 'hello worker',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'message' => 'hello worker',
            ]);

        $this->assertDatabaseCount('queue_requests', 1);
        $requestId = $response->json('request_id');
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertDatabaseHas('queue_requests', [
            'id' => $requestId,
            'status' => 'queued',
            'message' => 'hello worker',
        ]);

        Queue::assertPushed(DemoQueueJob::class, function (DemoQueueJob $job) {
            return $job->message === 'hello worker' && Str::isUuid($job->requestId);
        });
    }

    public function test_dispatch_batch_endpoint_queues_multiple_jobs(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/queue/demo/batch', [
            'total' => 3,
            'prefix' => 'demo',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'queued_jobs' => 3,
            ]);

        $this->assertDatabaseCount('queue_requests', 3);
        $this->assertCount(3, $response->json('request_ids'));
        Queue::assertPushed(DemoQueueJob::class, 3);
    }

    public function test_list_requests_endpoint_returns_all_requests(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/queue/demo', [
            'message' => 'first request',
        ]);
        $second = $this->postJson('/api/queue/demo', [
            'message' => 'second request',
        ]);

        $response = $this->getJson('/api/queue/demo/requests');

        $response->assertOk();
        $this->assertSame(2, $response->json('total'));
        $this->assertCount(2, $response->json('requests'));
        $this->assertContains($first->json('request_id'), array_column($response->json('requests'), 'id'));
        $this->assertContains($second->json('request_id'), array_column($response->json('requests'), 'id'));
    }

    public function test_queue_status_endpoint_returns_queue_request(): void
    {
        Queue::fake();

        $dispatchResponse = $this->postJson('/api/queue/demo', [
            'message' => 'status check',
        ]);

        $requestId = $dispatchResponse->json('request_id');

        $statusResponse = $this->getJson("/api/queue/demo/{$requestId}/status");

        $statusResponse->assertOk()
            ->assertJson([
                'request_id' => $requestId,
                'message' => 'status check',
                'status' => 'queued',
            ]);
    }
}
