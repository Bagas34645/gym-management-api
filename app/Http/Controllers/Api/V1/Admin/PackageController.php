<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\MembershipPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = MembershipPackage::query()->orderBy('name')->get();

        return $this->success($packages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:daily,weekly,monthly,yearly'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $package = MembershipPackage::query()->create($data);

        return $this->success($package, 'Paket berhasil dibuat', null, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $package = MembershipPackage::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:daily,weekly,monthly,yearly'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $package->update($data);

        return $this->success($package, 'Paket berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        $package = MembershipPackage::query()->findOrFail($id);
        $package->update(['status' => 'inactive']);

        return $this->success(null, 'Paket berhasil dinonaktifkan');
    }
}
