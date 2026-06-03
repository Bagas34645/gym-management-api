<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = Faq::query()
            ->where('status', 'active')
            ->with('category')
            ->orderBy('sort_order')
            ->get();

        return $this->success($faqs);
    }

    public function categories(): JsonResponse
    {
        $categories = FaqCategory::query()->orderBy('sort_order')->get();

        return $this->success($categories);
    }
}
