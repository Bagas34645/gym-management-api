<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherAnalyticsController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $data = $this->weatherService->getWeather($filters);

        return $this->success($data, 'OK', [
            'count' => $data->count(),
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success(
            $this->weatherService->getWeatherChart($filters),
        );
    }

    public function attendance(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $data = $this->weatherService->getWeatherAttendance($filters);

        return $this->success($data, 'OK', [
            'count' => $data->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success(
            $this->weatherService->getWeatherSummary($filters),
        );
    }

    public function distribution(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success(
            $this->weatherService->getWeatherDistribution($filters),
        );
    }

    /**
     * @return array{start_date?: string, end_date?: string}
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{start_date?: string, end_date?: string} $validated */
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        return array_filter($validated, fn ($value) => $value !== null && $value !== '');
    }
}
