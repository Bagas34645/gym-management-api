<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Imports\MembersImport;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->search($request);
    }

    public function search(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = $this->filterQuery($request);

        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sortBy) {
            'join_date' => $query->orderBy('created_at', $sortDir),
            'expired_date' => $query->leftJoin('memberships', function ($join) {
                $join->on('users.id', '=', 'memberships.user_id')->where('memberships.status', 'active');
            })->orderBy('memberships.end_date', $sortDir)->select('users.*'),
            default => $query->orderBy('name', $sortDir),
        };

        $paginator = $query->with('activeMembership')->paginate($perPage);

        $data = collect($paginator->items())->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'membership_status' => $u->activeMembership?->status ?? 'inactive',
            'expired_date' => $u->activeMembership?->end_date?->format('Y-m-d'),
        ]);

        return ApiResponse::paginated($data, $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'regex:/^08\d{8,11}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            ...$data,
            'role' => 'member',
            'status' => 'active',
        ]);

        return $this->success($user, 'Member berhasil ditambahkan', null, 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::query()
            ->with(['activeMembership.package', 'attendanceRecords', 'paymentRecords'])
            ->findOrFail($id);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'age' => $user->age,
            'height_cm' => $user->height_cm,
            'weight_kg' => $user->weight_kg,
            'fitness_goal' => $user->fitness_goal,
            'current_membership' => $user->activeMembership,
            'attendance_history' => $user->attendanceRecords->take(20),
            'payment_history' => $user->paymentRecords->take(20),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'phone' => ['sometimes', 'regex:/^08\d{8,11}$/', 'unique:users,phone,'.$id],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'age' => ['sometimes', 'integer'],
            'height_cm' => ['sometimes', 'integer'],
            'weight_kg' => ['sometimes', 'numeric'],
            'fitness_goal' => ['sometimes', 'string'],
        ]);

        $user->update($data);

        return $this->success($user, 'Member berhasil diperbarui');
    }

    public function destroy(string $id): JsonResponse
    {
        User::query()->findOrFail($id)->delete();

        return $this->success(null, 'Member berhasil dihapus');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv'],
            'override_existing' => ['sometimes', 'boolean'],
        ]);

        $import = new MembersImport((bool) $request->boolean('override_existing'));
        Excel::import($import, $request->file('file'));

        return $this->success([
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'errors' => count($import->errorRows),
            'error_rows' => $import->errorRows,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $users = $this->filterQuery($request)->get();
        $csv = "id,name,email,phone,status\n";
        foreach ($users as $u) {
            $csv .= "{$u->id},{$u->name},{$u->email},{$u->phone},{$u->status}\n";
        }

        $path = 'exports/members-'.now()->format('Y-m-d-His').'.csv';
        Storage::disk('public')->put($path, $csv);

        return $this->success([
            'download_url' => Storage::disk('public')->url($path),
            'expires_at' => now()->addHour()->toIso8601String(),
        ]);
    }

    private function filterQuery(Request $request)
    {
        $query = User::query()->where('role', 'member');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'expired') {
                $query->whereHas('activeMembership', fn ($q) => $q->where('end_date', '<', now()));
            } elseif ($status === 'active') {
                $query->whereHas('activeMembership', fn ($q) => $q->where('status', 'active'));
            } else {
                $query->where('status', $status);
            }
        }

        if ($packageId = $request->get('package_id')) {
            $query->whereHas('activeMembership', fn ($q) => $q->where('package_id', $packageId));
        }

        if ($from = $request->get('join_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->get('join_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }
}
