<?php

namespace App\Http\Controllers;

use App\Jobs\DemoQueueJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueDemoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:queued,processing,completed,failed',
        ]);

        $query = DB::table('queue_requests')
            ->orderByDesc('queued_at')
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $requests = $query->get();

        return response()->json([
            'total' => $requests->count(),
            'requests' => $requests,
        ]);
    }

    public function dispatchOne(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:255',
        ]);

        $message = $validated['message'] ?? 'Queued at '.now()->toDateTimeString();
        $requestId = (string) Str::uuid();

        DB::table('queue_requests')->insert([
            'id' => $requestId,
            'message' => $message,
            'status' => 'queued',
            'queued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DemoQueueJob::dispatch($requestId, $message);

        return response()->json([
            'status' => 'queued',
            'request_id' => $requestId,
            'message' => $message,
            'queue_connection' => config('queue.default'),
        ], 202);
    }

    public function dispatchBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total' => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:60',
        ]);

        $prefix = $validated['prefix'] ?? 'batch-job';
        $total = $validated['total'];
        $requestIds = [];

        for ($i = 1; $i <= $total; $i++) {
            $requestId = (string) Str::uuid();
            $message = $prefix.'-'.$i.'-'.Str::uuid();
            $requestIds[] = $requestId;

            DB::table('queue_requests')->insert([
                'id' => $requestId,
                'message' => $message,
                'status' => 'queued',
                'queued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DemoQueueJob::dispatch($requestId, $message);
        }

        return response()->json([
            'status' => 'queued',
            'queued_jobs' => $total,
            'request_ids' => $requestIds,
            'queue_connection' => config('queue.default'),
        ], 202);
    }

    public function status(string $requestId): JsonResponse
    {
        $queueRequest = DB::table('queue_requests')
            ->where('id', $requestId)
            ->first();

        if (! $queueRequest) {
            return response()->json([
                'message' => 'Queue request not found.',
            ], 404);
        }

        return response()->json([
            'request_id' => $queueRequest->id,
            'message' => $queueRequest->message,
            'status' => $queueRequest->status,
            'error_message' => $queueRequest->error_message,
            'queued_at' => $queueRequest->queued_at,
            'started_at' => $queueRequest->started_at,
            'finished_at' => $queueRequest->finished_at,
        ]);
    }
}
