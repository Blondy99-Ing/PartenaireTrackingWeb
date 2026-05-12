<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Services\Leases\DashboardLeaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashbaordLeaseController extends Controller
{
    public function __construct(
        private readonly DashboardLeaseService $dashboardLeaseService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'period',
            'type',
            'status',
            'search',
        ]);

        try {
            $dashboard = $this->dashboardLeaseService->build(
                $request->user(),
                $filters
            );

            return view('leases.dashboard', [
                'dashboard' => $dashboard,
                'filters' => $dashboard['filters'] ?? $filters,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_DASHBOARD_PAGE_FAILED]', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return view('leases.dashboard', [
                'dashboard' => $this->emptyDashboard(),
                'filters' => $filters,
                'pageError' => 'Le dashboard recouvrement est momentanément indisponible. Veuillez réessayer.',
            ]);
        }
    }

    private function emptyDashboard(): array
    {
        return [
            'filters' => [
                'period' => 'today',
                'type' => 'all',
                'status' => 'all',
                'search' => '',
            ],
            'period' => [
                'key' => 'today',
                'label' => 'Aujourd’hui',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
            'warnings' => [],
            'kpis' => [
                'recovery_rate' => 0,
                'expected_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'drivers_to_call' => 0,
                'unpaid_leases_count' => 0,
                'cutoffs_to_follow' => 0,
                'cutoffs_confirmed' => 0,
                'active_contracts' => 0,
                'vehicles_count' => 0,
            ],
            'priorities' => [],
            'charts' => [
                'recovery' => [
                    'labels' => [],
                    'expected' => [],
                    'paid' => [],
                    'remaining' => [],
                ],
                'type_breakdown' => [
                    'total' => 0,
                    'items' => [],
                ],
            ],
            'tables' => [
                'drivers_risk' => [],
                'payments_today' => [],
                'cutoffs' => [],
            ],
            'contracts_summary' => [
                'main_active' => 0,
                'sub_active' => 0,
                'sold' => 0,
                'suspended' => 0,
                'remaining_total' => 0,
            ],
            'cutoff_summary' => [
                'planned' => 0,
                'waiting_stop' => 0,
                'command_sent' => 0,
                'confirmed' => 0,
                'cancelled_paid' => 0,
                'gps_failed' => 0,
            ],
        ];
    }
}