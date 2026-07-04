<?php

namespace App\Http\Controllers\Api\Internal\Lease;

use App\Http\Controllers\Controller;
use App\Services\Leases\LeaseContractTypeService;
use Illuminate\Http\Request;

class SubContractTypeController extends Controller
{
    public function __construct(
        private readonly LeaseContractTypeService $service
    ) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getSubContractTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'libelle' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
        ]);

        $created = $this->service->createSubContractType($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sous-contrat créé avec succès.',
            'data' => $created,
        ]);
    }
}