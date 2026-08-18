<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UnifiedCutoffHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnifiedCutoffHistoryController extends Controller
{
    public function __construct(private readonly UnifiedCutoffHistoryService $service)
    {
    }

    public function index(Request $request): View
    {
        /** @var User $actor */
        $actor = $request->user();

        $filters = $request->only(['period', 'specific_date', 'date_from', 'date_to', 'source', 'direction', 'status', 'search', 'page']);

        $history = $this->service->getMergedHistory($actor, $filters);
        $summary = $this->service->getSummary($actor, $filters);

        return view('cutoff.unified-history', [
            'history' => $history,
            'summary' => $summary,
            'filters' => $filters,
            'availableDirections' => $this->service->getAvailableDirections(),
            'availableStatuses' => $this->service->getAvailableStatuses(),
        ]);
    }
}
