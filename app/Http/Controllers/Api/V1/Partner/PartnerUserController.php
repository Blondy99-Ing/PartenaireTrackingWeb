<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partner\StorePartnerDriverRequest;
use App\Http\Requests\Api\V1\Partner\UpdatePartnerDriverRequest;
use App\Http\Resources\V1\Partner\UserResource;
use App\Services\Partner\PartnerDriverService;
use Illuminate\Http\Request;

class PartnerUserController extends Controller
{
    public function __construct(private PartnerDriverService $service) {}

    public function index(Request $request)
    {
        $partner = $request->user();
        $users = $this->service->listDrivers($partner);

        return response()->json([
            'ok' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    public function store(StorePartnerDriverRequest $request)
    {
        $partner = $request->user();
        $user = $this->service->createDriver($partner, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Utilisateur créé',
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(UpdatePartnerDriverRequest $request, $id)
    {
        $partner = $request->user();
        $user = $this->service->updateDriver($partner, (int) $id, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Utilisateur mis à jour',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $partner = $request->user();
        $this->service->deleteDriver($partner, (int) $id);

        return response()->json(null, 204);
    }
}
