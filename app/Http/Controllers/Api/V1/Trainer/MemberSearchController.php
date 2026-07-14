<?php

namespace App\Http\Controllers\Api\V1\Trainer;

use App\Http\Controllers\Api\V1\Admin\MemberController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberSearchController extends MemberController
{
    public function search(Request $request): JsonResponse
    {
        return parent::search($request);
    }
}
