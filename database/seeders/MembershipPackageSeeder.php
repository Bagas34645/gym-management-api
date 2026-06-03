<?php

namespace Database\Seeders;

use App\Models\MembershipPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MembershipPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Daily Pass',
                'type' => 'daily',
                'duration_days' => 1,
                'price' => 50000,
                'description' => 'Single day gym access',
                'benefits' => ['Gym floor access', 'Locker room'],
            ],
            [
                'name' => 'Weekly Pass',
                'type' => 'weekly',
                'duration_days' => 7,
                'price' => 300000,
                'description' => '7 days unlimited access',
                'benefits' => ['Gym floor access', 'Locker room', 'Group classes'],
            ],
            [
                'name' => 'Monthly Pass',
                'type' => 'monthly',
                'duration_days' => 30,
                'price' => 1000000,
                'description' => '30 days unlimited access',
                'benefits' => ['Gym floor access', 'Locker room', 'Group classes', 'Sauna'],
            ],
            [
                'name' => 'Yearly Pass',
                'type' => 'yearly',
                'duration_days' => 365,
                'price' => 10000000,
                'description' => 'Full year premium membership',
                'benefits' => ['All facilities', 'Personal trainer discount', 'Priority booking'],
            ],
        ];

        foreach ($packages as $package) {
            MembershipPackage::query()->firstOrCreate(
                ['name' => $package['name']],
                array_merge($package, ['id' => Str::uuid()->toString(), 'status' => 'active'])
            );
        }
    }
}
