<?php

namespace App\Http\Controllers;

use App\Actions\Organization\RefreshYandexOrganizationAction;
use App\Actions\Organization\SaveYandexOrganizationAction;
use App\Http\Requests\StoreYandexOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
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
}
