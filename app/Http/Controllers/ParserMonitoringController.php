<?php

namespace App\Http\Controllers;

use App\Enums\ParsingStatus;
use App\Http\Resources\ParserErrorResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParserMonitoringController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $query = Organization::query()->whereBelongsTo($request->user());

        $failed = (clone $query)->where('parsing_status', ParsingStatus::Failed)->count();
        $processing = (clone $query)->where('parsing_status', ParsingStatus::Processing)->count();
        $pending = (clone $query)->where('parsing_status', ParsingStatus::Pending)->count();
        $success = (clone $query)->where('parsing_status', ParsingStatus::Success)->count();

        $recentErrors = (clone $query)
            ->where('parsing_status', ParsingStatus::Failed)
            ->whereNotNull('parsing_error')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'counts' => compact('failed', 'processing', 'pending', 'success'),
                'recent_errors' => ParserErrorResource::collection($recentErrors)->resolve(),
            ],
        ]);
    }
}
