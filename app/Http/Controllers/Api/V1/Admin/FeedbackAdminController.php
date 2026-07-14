<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = Feedback::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $paginator->getCollection()->transform(function (Feedback $feedback) {
            if ($feedback->is_anonymous) {
                $feedback->unsetRelation('user');
                $feedback->setAttribute('user', null);
            }

            return $feedback;
        });

        return $this->paginated($paginator);
    }
}
