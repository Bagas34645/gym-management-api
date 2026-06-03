<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\User;
use Illuminate\Database\Seeder;

class MembershipSeeder extends Seeder
{
    public function run(): void
    {
        $packages = MembershipPackage::query()->get()->keyBy('type');
        $members = User::query()->where('role', 'member')->whereDoesntHave('trainer')->limit(10)->get();

        foreach ($members->take(8) as $index => $member) {
            $package = match (true) {
                $index < 5 => $packages['monthly'] ?? $packages->first(),
                $index < 7 => $packages['weekly'] ?? $packages->first(),
                default => $packages['daily'] ?? $packages->first(),
            };

            $startDate = now()->subDays(fake()->numberBetween(1, 20));
            $endDate = match (true) {
                $index === 6 => now()->addDays(3),
                $index === 7 => now()->subDays(1),
                default => $startDate->copy()->addDays($package->duration_days),
            };

            if ($index === 7) {
                $startDate = now()->subDays($package->duration_days + 2);
                $endDate = $startDate->copy()->addDays($package->duration_days);
            }

            $status = match (true) {
                $index === 7 => 'expired',
                $index === 6 => 'active',
                default => 'active',
            };

            if ($status === 'active' && Membership::query()->where('user_id', $member->id)->where('status', 'active')->exists()) {
                continue;
            }

            Membership::query()->create([
                'user_id' => $member->id,
                'package_id' => $package->id,
                'status' => $status,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'payment_method' => fake()->randomElement(['transfer', 'cash', 'qris']),
                'payment_status' => 'completed',
            ]);
        }
    }
}
