<?php

namespace App\Http\Controllers\Api;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\PolydockStore;
use Illuminate\Http\JsonResponse;

class RegionsController extends Controller
{
    /**
     * Get all public regions with their available apps
     */
    public function index(): JsonResponse
    {
        try {
            $regions = PolydockStore::query()
                ->where('status', PolydockStoreStatusEnum::PUBLIC)
                ->where('listed_in_marketplace', true)
                ->with(['apps' => function ($query) {
                    $query->where('status', PolydockStoreAppStatusEnum::AVAILABLE);
                }])
                ->get()
                ->map(fn ($store) => [
                    'uuid' => null, // Stores don't have UUIDs, using ID as identifier
                    'id' => $store->id,
                    'label' => $store->name,
                    'apps' => $store->apps->map(fn ($app) => [
                        'uuid' => $app->uuid,
                        'label' => $app->name,
                    ]),
                ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Regions and apps retrieved successfully',
                'data' => [
                    'regions' => $regions,
                ],
                'status_code' => 200,
            ], 200);
        } catch (\Exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve regions and apps',
                'data' => null,
                'status_code' => 500,
            ], 500);
        }
    }
}
