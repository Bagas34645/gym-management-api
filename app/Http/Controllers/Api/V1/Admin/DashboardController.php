<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use App\Models\Membership;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $totalMembers = User::query()->where('role', 'member')->count();
        $activeMembers = User::query()->where('role', 'member')->whereHas('activeMembership')->count();
        $todayCheckins = AttendanceRecord::query()->whereDate('check_in_time', today())->count();
        $weekCheckins = AttendanceRecord::query()->whereBetween('check_in_time', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $monthCheckins = AttendanceRecord::query()->whereMonth('check_in_time', now()->month)->count();

        $revenueToday = PaymentRecord::query()->whereDate('payment_date', today())->where('status', 'completed')->sum('amount');
        $revenueMonth = PaymentRecord::query()
            ->whereMonth('payment_date', now()->month)
            ->where('status', 'completed')
            ->sum('amount');
        $revenueLastMonth = PaymentRecord::query()
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->where('status', 'completed')
            ->sum('amount');

        $growth = $revenueLastMonth > 0
            ? round((($revenueMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        return $this->success([
            'members' => [
                'total' => $totalMembers,
                'active' => $activeMembers,
                'inactive' => $totalMembers - $activeMembers,
                'new_this_month' => User::query()->where('role', 'member')->whereMonth('created_at', now()->month)->count(),
            ],
            'attendance' => [
                'today' => $todayCheckins,
                'this_week' => $weekCheckins,
                'this_month' => $monthCheckins,
            ],
            'revenue' => [
                'today' => (float) $revenueToday,
                'this_month' => (float) $revenueMonth,
                'growth_percent' => $growth,
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'metric' => ['required', 'in:members,attendance,revenue'],
            'period' => ['nullable', 'in:7d,30d,90d,1y'],
            'group_by' => ['nullable', 'in:day,week,month'],
        ]);

        $days = match ($data['period'] ?? '30d') {
            '7d' => 7, '90d' => 90, '1y' => 365, default => 30,
        };
        $from = now()->subDays($days)->startOfDay();
        $groupBy = $data['group_by'] ?? 'day';

        $timeline = match ($data['metric']) {
            'members' => User::query()
                ->where('role', 'member')
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) as date, count(*) as value')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'attendance' => AttendanceRecord::query()
                ->where('check_in_time', '>=', $from)
                ->selectRaw('DATE(check_in_time) as date, count(*) as value')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'revenue' => PaymentRecord::query()
                ->where('payment_date', '>=', $from->toDateString())
                ->where('status', 'completed')
                ->selectRaw('payment_date as date, sum(amount) as value')
                ->groupBy('payment_date')
                ->orderBy('payment_date')
                ->get(),
        };

        return $this->success([
            'metric' => $data['metric'],
            'period' => $data['period'] ?? '30d',
            'group_by' => $groupBy,
            'timeline' => $timeline,
        ]);
    }
}
