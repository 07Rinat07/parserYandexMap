<?php

namespace App\Http\Controllers;

use App\Actions\Organization\RefreshYandexOrganizationAction;
use App\Actions\Organization\SaveYandexOrganizationAction;
use App\Http\Requests\StoreYandexOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\RatingSnapshotResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = Organization::query()
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->get();

        return OrganizationResource::collection($organizations);
    }

    public function show(Request $request): OrganizationResource|JsonResponse
    {
        $organization = Organization::query()
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->first();

        return $organization
            ? OrganizationResource::make($organization)
            : response()->json(['data' => null]);
    }

    public function showById(Request $request, Organization $organization): OrganizationResource
    {
        abort_unless($organization->user_id === $request->user()->id, 404);

        return OrganizationResource::make($organization);
    }

    public function store(
        StoreYandexOrganizationRequest $request,
        SaveYandexOrganizationAction $action,
    ): JsonResponse {
        $organization = $action->execute($request->user(), $request->string('yandex_url')->toString());

        return OrganizationResource::make($organization)->response()->setStatusCode(200);
    }

    public function refresh(Request $request, RefreshYandexOrganizationAction $action): OrganizationResource
    {
        $organization = Organization::query()
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->firstOrFail();

        return OrganizationResource::make($action->execute($organization));
    }

    public function refreshById(
        Request $request,
        Organization $organization,
        RefreshYandexOrganizationAction $action,
    ): OrganizationResource {
        abort_unless($organization->user_id === $request->user()->id, 404);

        return OrganizationResource::make($action->execute($organization));
    }

    public function history(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($organization->user_id === $request->user()->id, 404);

        $snapshots = $organization->ratingSnapshots()
            ->latest('captured_at')
            ->limit(30)
            ->get();

        return RatingSnapshotResource::collection($snapshots);
    }

    public function snapshot(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($organization->user_id === $request->user()->id, 404);
        abort_if($organization->rating === null && $organization->ratings_count === null && $organization->reviews_count === null, 422, 'Нет данных для снимка рейтинга.');

        $organization->ratingSnapshots()->create([
            'rating' => $organization->rating,
            'ratings_count' => $organization->ratings_count,
            'reviews_count' => $organization->reviews_count,
            'captured_at' => now(),
        ]);

        return $this->history($request, $organization);
    }
}
