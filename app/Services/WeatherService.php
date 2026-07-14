<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeatherService
{
    public function getWeather(array $filters = []): Collection
    {
        return $this->weatherQuery($filters)
            ->orderBy('weather_date', 'desc')
            ->get();
    }

    public function getWeatherChart(array $filters = []): Collection
    {
        $attendance = $this->attendanceByDate($filters);

        return $this->weatherQuery($filters)
            ->orderBy('weather_date')
            ->get()
            ->map(function ($item) use ($attendance) {
                $date = (string) $item->weather_date;
                $visitor = $attendance[$date] ?? null;

                return [
                    'date' => $date,
                    'temperature' => data_get($item, 'temperature.avg'),
                    'precipitation' => data_get($item, 'precipitation.mm'),
                    'comfort_score' => $item->comfort_score,
                    'weather' => data_get($item, 'weather.category'),
                    'visitors' => (int) ($visitor?->visitor_count ?? 0),
                ];
            });
    }

    public function getWeatherAttendance(array $filters = []): Collection
    {
        $attendance = $this->attendanceByDate($filters);

        return $this->weatherQuery($filters)
            ->orderBy('weather_date')
            ->get()
            ->map(function ($weather) use ($attendance) {
                $date = (string) $weather->weather_date;
                $visitor = $attendance[$date] ?? null;

                return [
                    'weather_date' => $date,
                    'visitor_count' => (int) ($visitor?->visitor_count ?? 0),
                    'comfort_score' => $weather->comfort_score,
                    'temperature_avg' => data_get($weather, 'temperature.avg'),
                    'temperature_min' => data_get($weather, 'temperature.min'),
                    'temperature_max' => data_get($weather, 'temperature.max'),
                    'weather_category' => data_get($weather, 'weather.category'),
                    'weather_severity' => data_get($weather, 'weather.severity'),
                    'precipitation_mm' => data_get($weather, 'precipitation.mm'),
                    'wind_speed_kmh' => data_get($weather, 'wind.speed_kmh'),
                    'is_weekend' => (bool) data_get($weather, 'day.is_weekend'),
                ];
            });
    }

    public function getWeatherSummary(array $filters = []): array
    {
        $weatherData = $this->weatherQuery($filters)->get();

        if ($weatherData->isEmpty()) {
            return [
                'total_days' => 0,
                'total_visitors' => 0,
                'average_temperature' => 0,
                'average_comfort_score' => 0,
                'rainy_days' => 0,
            ];
        }

        $attendance = $this->attendanceByDate($filters);
        $totalVisitors = 0;

        foreach ($weatherData as $weather) {
            $date = (string) $weather->weather_date;
            $visitor = $attendance[$date] ?? null;
            $totalVisitors += (int) ($visitor?->visitor_count ?? 0);
        }

        return [
            'total_days' => $weatherData->count(),
            'total_visitors' => $totalVisitors,
            'average_temperature' => round(
                (float) $weatherData->avg(fn ($item) => data_get($item, 'temperature.avg')),
                2,
            ),
            'average_comfort_score' => round(
                (float) $weatherData->avg('comfort_score'),
                2,
            ),
            'rainy_days' => $weatherData
                ->filter(fn ($item) => (float) data_get($item, 'precipitation.mm') > 0)
                ->count(),
        ];
    }

    public function getWeatherDistribution(array $filters = []): Collection
    {
        return $this->weatherQuery($filters)
            ->get()
            ->groupBy(fn ($item) => data_get($item, 'weather.category') ?? 'Unknown')
            ->map(fn ($items, $category) => [
                'weather_category' => $category,
                'total_days' => $items->count(),
            ])
            ->values();
    }

    private function weatherQuery(array $filters)
    {
        $query = DB::connection('mongodb')->table('weather_processed');

        if (! empty($filters['start_date'])) {
            $query->where('weather_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('weather_date', '<=', $filters['end_date']);
        }

        return $query;
    }

    private function attendanceByDate(array $filters): Collection
    {
        $query = DB::table('attendance_records')
            ->selectRaw("
                DATE(check_in_time AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Jakarta') as weather_date,
                COUNT(*) as visitor_count
            ")
            ->groupByRaw("DATE(check_in_time AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Jakarta')");

        if (! empty($filters['start_date'])) {
            $query->whereRaw(
                "DATE(check_in_time AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Jakarta') >= ?",
                [$filters['start_date']],
            );
        }

        if (! empty($filters['end_date'])) {
            $query->whereRaw(
                "DATE(check_in_time AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Jakarta') <= ?",
                [$filters['end_date']],
            );
        }

        return $query
            ->get()
            ->keyBy(fn ($row) => (string) $row->weather_date);
    }
}
