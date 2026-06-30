<?php

namespace Database\Seeders;

use App\Models\NotificationPreference;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@gym.local'],
            [
                'name' => 'Super Admin',
                'phone' => '08100000001',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@gym.local'],
            [
                'name' => 'Gym Admin',
                'phone' => '08100000002',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'admin2@gym.local'],
            [
                'name' => 'Gym Admin 2',
                'phone' => '08100000003',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        $trainerUsers = User::factory()->count(4)->create([
            'role' => 'member',
            'status' => 'active',
        ]);

        foreach ($trainerUsers as $index => $user) {
            $user->update(['role' => 'member']);
            Trainer::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => fake()->randomElement(['Strength', 'Cardio', 'HIIT', 'Yoga']),
                    'experience_years' => fake()->numberBetween(2, 15),
                    'certification' => 'ACE Certified',
                    'bio' => fake()->paragraph(),
                    'hourly_rate' => fake()->randomFloat(2, 100000, 300000),
                    'status' => 'active',
                ]
            );
        }

        User::factory()->count(10)->create([
            'role' => 'member',
            'status' => 'active',
        ]);

        foreach (User::query()->where('role', 'member')->get() as $member) {
            NotificationPreference::query()->firstOrCreate(
                ['user_id' => $member->id],
                [
                    'workout_reminder_days' => ['monday', 'wednesday', 'friday'],
                ]
            );
        }

        unset($superAdmin, $admin);
    }
}
