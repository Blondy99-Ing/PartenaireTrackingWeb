<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leases\LeaseCutoffHistoryIndexRequest;
use App\Services\Leases\LeaseCutoffHistoryService;
use Illuminate\Contracts\View\View;

class LeaseCutoffHistoryController extends Controller
{
    public function __construct(
        private readonly LeaseCutoffHistoryService $service
    ) {
    }

    public function index(LeaseCutoffHistoryIndexRequest $request): View
    {
        $filters = $request->validated();

        $histories = $this->service->getPaginatedHistory(auth()->user(), $filters);
        $summary = $this->service->getSummary(auth()->user(), $filters);
        $statuses = $this->service->getAvailableStatuses();

        return view('leases.cutoff-history', [
            'histories' => $histories,
            'summary' => $summary,
            'statuses' => $statuses,
            'filters' => $filters,
        ]);
    }
}