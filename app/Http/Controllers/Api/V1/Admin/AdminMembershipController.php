<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Services\Membership\MembershipFulfillmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminMembershipController extends Controller
{
    public function __construct(
        private readonly MembershipFulfillmentService $fulfillmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        $query = User::query()->with(['activeMembership.package']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'membership_status' => $u->activeMembership?->status ?? 'inactive',
            'expired_date' => $u->activeMembership?->end_date?->format('Y-m-d'),
        ]);

        return ApiResponse::paginated(
            $data,
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
        );
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'package_id' => ['required', 'uuid', 'exists:membership_packages,id'],
            'payment_method' => ['required', 'in:transfer,cash,qris'],
            'start_date' => ['nullable', 'date'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);
        $package = MembershipPackage::query()->findOrFail($data['package_id']);
        $start = isset($data['start_date']) ? now()->parse($data['start_date']) : now();
        $end = $start->copy()->addDays($package->duration_days);

        Membership::query()->where('user_id', $user->id)->where('status', 'active')->update(['status' => 'expired']);

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'payment_method' => $data['payment_method'],
            'payment_status' => 'completed',
        ]);

        PaymentRecord::query()->create([
            'user_id' => $user->id,
            'membership_id' => $membership->id,
            'amount' => $package->price,
            'payment_method' => $data['payment_method'],
            'payment_date' => now()->toDateString(),
            'reference_number' => 'PAY-'.strtoupper(Str::random(10)),
            'status' => 'completed',
        ]);

        return $this->success($membership->load('package'), 'Membership berhasil diaktivasi', null, 201);
    }

    public function renew(Request $request, string $id): JsonResponse
    {
        $membership = Membership::query()->with('package', 'user')->findOrFail($id);
        $data = $request->validate([
            'package_id' => ['sometimes', 'uuid', 'exists:membership_packages,id'],
            'payment_method' => ['required', 'in:transfer,cash,qris'],
        ]);

        $package = isset($data['package_id'])
            ? MembershipPackage::query()->findOrFail($data['package_id'])
            : $membership->package;

        $newEnd = $membership->end_date->copy()->addDays($package->duration_days);

        MembershipRenewal::query()->create([
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'package_id' => $package->id,
            'previous_end_date' => $membership->end_date,
            'new_end_date' => $newEnd,
            'status' => 'approved',
            'payment_method' => $data['payment_method'],
            'amount_paid' => $package->price,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $membership->update([
            'end_date' => $newEnd,
            'package_id' => $package->id,
            'payment_method' => $data['payment_method'],
            'payment_status' => 'completed',
        ]);

        return $this->success([
            'membership_id' => $membership->id,
            'new_end_date' => $newEnd->format('Y-m-d'),
        ], 'Membership berhasil diperpanjang');
    }

    public function renewals(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $status = $request->get('status', 'pending_verification');

        $query = MembershipRenewal::query()
            ->with(['user', 'package'])
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (MembershipRenewal $r) => [
            'id' => $r->id,
            'member_name' => $r->user->name,
            'email' => $r->user->email,
            'package_name' => $r->package->name,
            'amount_paid' => (float) $r->amount_paid,
            'payment_method' => $r->payment_method,
            'payment_proof_url' => $r->payment_proof_url,
            'previous_end_date' => $r->previous_end_date?->format('Y-m-d'),
            'new_end_date' => $r->new_end_date->format('Y-m-d'),
            'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601String(),
        ]);

        return ApiResponse::paginated($data, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    public function approveRenewal(Request $request, string $id): JsonResponse
    {
        $renewal = MembershipRenewal::query()->with(['membership', 'package'])->findOrFail($id);

        if ($renewal->status !== 'pending_verification') {
            throw new ApiException('Renewal sudah diproses', null, 400);
        }

        $membership = $this->fulfillmentService->fulfill(
            $renewal,
            verifiedBy: $request->user()->id,
        );

        return $this->success([
            'membership_id' => $membership->id,
            'status' => $membership->status,
            'end_date' => $membership->end_date->format('Y-m-d'),
        ], 'Renewal disetujui dan membership diaktifkan');
    }

    public function rejectRenewal(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $renewal = MembershipRenewal::query()->with('membership')->findOrFail($id);

        if ($renewal->status !== 'pending_verification') {
            throw new ApiException('Renewal sudah diproses', null, 400);
        }

        $this->fulfillmentService->reject($renewal, verifiedBy: $request->user()->id);

        return $this->success(null, 'Renewal ditolak');
    }

    public function expired(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $daysBefore = (int) $request->get('days_before', 7);
        $status = $request->get('status', 'expiring_soon');

        $query = Membership::query()->with(['user', 'package']);

        if ($status === 'expired') {
            $query->where('status', 'expired')
                ->orWhere(fn ($q) => $q->where('status', 'active')->where('end_date', '<', now()->toDateString()));
        } else {
            $query->expiringSoon($daysBefore);
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn ($m) => [
            'membership_id' => $m->id,
            'member_name' => $m->user->name,
            'email' => $m->user->email,
            'package_name' => $m->package->name,
            'end_date' => $m->end_date->format('Y-m-d'),
            'status' => $m->status,
        ]);

        return ApiResponse::paginated($data, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }
}
