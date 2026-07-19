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
        $filters = $request->only(['search', 'period', 'date', 'start_date', 'end_date']);

        try {
            $dashboard = $this->dashboardLeaseService->build($request->user(), $filters);

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
                'pageError' => 'Le dashboard recouvrement est momentanément indisponible.',
            ]);
        }
    }

    private function emptyDashboard(): array
    {
        return [
            'filters' => [
                'search' => '',
                'period' => 'today',
                'date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
            'period' => [
                'key' => 'today',
                'label' => 'Aujourd’hui',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
            ],
            'week_period' => [
                'key' => 'current_week',
                'label' => 'Semaine courante',
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->endOfWeek()->toDateString(),
            ],
            'warnings' => [],
            'kpis' => [
                'recovery_rate' => 0,
                'expected_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'drivers_paid' => 0,
                'total_expected_drivers' => 0,
                'drivers_unpaid' => 0,
                'unpaid_leases_count' => 0,
                'vehicles_count' => 0,
                'vehicles_used' => 0,
                'vehicles_never_used' => 0,
            ],
            'charts' => [
                'recovery' => [
                    'labels' => [],
                    'dates' => [],
                    'expected' => [],
                    'paid' => [],
                    'remaining' => [],
                    'rate' => [],
                ],
                'type_recovery' => [
                    'items' => [],
                    'total_expected' => 0,
                    'total_paid' => 0,
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
                'command_sent' => 0,
                'confirmed' => 0,
                'gps_failed' => 0,
            ],
            'overdue_ledger' => [
                'drivers' => [],
                'overdue_count' => 0,
                'up_to_date_count' => null,
                'total_due' => 0,
                'oldest_overdue_days' => 0,
            ],
            'payments_summary' => [
                'total_amount' => 0,
                'payments_count' => 0,
                'drivers_count' => 0,
                'by_driver' => [],
            ],
        ];
    }
}
