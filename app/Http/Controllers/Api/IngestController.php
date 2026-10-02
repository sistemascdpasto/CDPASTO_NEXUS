<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestRequest;
use App\Services\TelemetryIngestor;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __invoke(IngestRequest $request, TelemetryIngestor $ingestor): JsonResponse
    {
        $counts = $ingestor->ingest($request->attributes->get('application'), $request->validated());

        return response()->json(['ok' => true, 'stored' => $counts]);
    }
}
