<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewIndexRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): JsonResponse
    {
        $organization = Organization::query()
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->firstOrFail();

        return $this->reviewsResponse($request, $organization);
    }

    public function indexForOrganization(ReviewIndexRequest $request, Organization $organization): JsonResponse
    {
        abort_unless($organization->user_id === $request->user()->id, 404);

        return $this->reviewsResponse($request, $organization);
    }

    private function reviewsResponse(ReviewIndexRequest $request, Organization $organization): JsonResponse
    {
        $reviews = $organization->reviews()
            ->orderByRaw('review_date IS NULL')
            ->orderByDesc('review_date')
            ->latest('id')
            ->paginate(
                perPage: min((int) $request->integer('per_page', 50), 50),
            );

        return response()->json([
            'data' => ReviewResource::collection($reviews->getCollection())->resolve(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }
}
