<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
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
            'members' => $this->aggregateTimeline(
                User::query()
                    ->where('role', 'member')
                    ->where('created_at', '>=', $from),
                'created_at',
                $groupBy,
            ),
            'attendance' => $this->aggregateTimeline(
                AttendanceRecord::query()
                    ->where('check_in_time', '>=', $from),
                'check_in_time',
                $groupBy,
            ),
            'revenue' => $this->aggregateTimeline(
                PaymentRecord::query()
                    ->where('payment_date', '>=', $from->toDateString())
                    ->where('status', 'completed'),
                'payment_date',
                $groupBy,
                true,
            ),
        };

        return $this->success([
            'metric' => $data['metric'],
            'period' => $data['period'] ?? '30d',
            'group_by' => $groupBy,
            'timeline' => $timeline,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Support\Collection<int, object{date: string, value: float|int}>
     */
    private function aggregateTimeline($query, string $column, string $groupBy, bool $sum = false): \Illuminate\Support\Collection
    {
        $aggregate = $sum ? 'sum(amount)' : 'count(*)';
        $dateExpression = $this->dateBucketExpression($column, $groupBy);

        return $query
            ->selectRaw("{$dateExpression} as date, {$aggregate} as value")
            ->groupByRaw($dateExpression)
            ->orderByRaw($dateExpression)
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'value' => $sum ? (float) $row->value : (int) $row->value,
            ]);
    }

    private function dateBucketExpression(string $column, string $groupBy): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return match ($groupBy) {
                'week' => "DATE_TRUNC('week', {$column})::date",
                'month' => "DATE_TRUNC('month', {$column})::date",
                default => "({$column})::date",
            };
        }

        return match ($groupBy) {
            'week' => "DATE(DATE_SUB({$column}, INTERVAL WEEKDAY({$column}) DAY))",
            'month' => "DATE_FORMAT({$column}, '%Y-%m-01')",
            default => "DATE({$column})",
        };
    }
}
