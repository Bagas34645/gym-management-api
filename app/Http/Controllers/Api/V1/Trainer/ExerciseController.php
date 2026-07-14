<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = Exercise::query()->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $paginator = $query->paginate($perPage);

        return $this->paginated($paginator);
    }
}
