<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $membership = $request->user()->activeMembership?->load('package');

        if (! $membership) {
            return $this->success(null, 'Tidak ada membership aktif');
        }

        return $this->success([
            'membership_id' => $membership->id,
            'package_name' => $membership->package->name,
            'status' => $membership->status,
            'start_date' => $membership->start_date->format('Y-m-d'),
            'end_date' => $membership->end_date->format('Y-m-d'),
            'remaining_days' => max(0, now()->diffInDays($membership->end_date, false)),
            'price' => (float) $membership->package->price,
        ]);
    }

    public function packages(): JsonResponse
    {
        $packages = MembershipPackage::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => $this->packagePayload($p));

        return $this->success($packages);
    }

    public function packageShow(string $id): JsonResponse
    {
        $package = MembershipPackage::query()->findOrFail($id);

        return $this->success($this->packagePayload($package));
    }

    public function renew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'uuid', 'exists:membership_packages,id'],
            'payment_method' => ['required', 'in:transfer,cash,qris'],
            'payment_proof' => ['required_if:payment_method,transfer', 'file', 'max:5120'],
        ]);

        $package = MembershipPackage::query()->where('status', 'active')->find($data['package_id']);

        if (! $package) {
            throw new ApiException('Paket membership tidak ditemukan atau tidak aktif', ErrorCode::MembershipPackageInvalid, 400);
        }

        $user = $request->user();
        $current = $user->activeMembership;
        $previousEnd = $current?->end_date ?? now()->toDateString();
        $newEnd = now()->parse($previousEnd)->addDays($package->duration_days)->toDateString();

        $proofUrl = null;
        if ($request->hasFile('payment_proof')) {
            $proofUrl = $request->file('payment_proof')->store('payment-proofs', 'public');
            $proofUrl = 'storage/'.$proofUrl;
        }

        $renewal = MembershipRenewal::query()->create([
            'membership_id' => $current?->id,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'previous_end_date' => $previousEnd,
            'new_end_date' => $newEnd,
            'status' => 'pending_verification',
            'payment_method' => $data['payment_method'],
            'payment_proof_url' => $proofUrl,
            'amount_paid' => $package->price,
        ]);

        return $this->success([
            'renewal_id' => $renewal->id,
            'status' => $renewal->status,
            'package' => $package->name,
            'new_end_date' => $newEnd,
        ], 'Renewal berhasil', null, 201);
    }

    public function history(Request $request): JsonResponse
    {
        $history = MembershipRenewal::query()
            ->where('user_id', $request->user()->id)
            ->with('package')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'package_name' => $r->package->name,
                'status' => $r->status,
                'previous_end_date' => $r->previous_end_date?->format('Y-m-d'),
                'new_end_date' => $r->new_end_date->format('Y-m-d'),
                'amount_paid' => (float) $r->amount_paid,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $this->success($history);
    }

    private function packagePayload(MembershipPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'type' => $package->type,
            'duration_days' => $package->duration_days,
            'price' => (float) $package->price,
            'description' => $package->description,
            'benefits' => $package->benefits,
            'status' => $package->status,
        ];
    }
}
