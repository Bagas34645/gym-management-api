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
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@coregym.id'],
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

        $demoTrainer = User::query()->firstOrCreate(
            ['email' => 'trainer@gym.local'],
            [
                'name' => 'Demo Trainer',
                'phone' => '08100000099',
                'password' => Hash::make('password'),
                'role' => 'trainer',
                'status' => 'active',
                'is_verified' => true,
            ]
        );
        $demoTrainer->update(['role' => 'trainer']);
        Trainer::query()->firstOrCreate(
            ['user_id' => $demoTrainer->id],
            [
                'specialization' => 'Strength',
                'experience_years' => 5,
                'certification' => 'ACE Certified',
                'bio' => 'Demo trainer account for portal testing.',
                'hourly_rate' => 150000,
                'status' => 'active',
            ]
        );

        $trainerUsers = User::factory()->count(4)->create([
            'role' => 'trainer',
            'status' => 'active',
            'is_verified' => true,
        ]);

        foreach ($trainerUsers as $index => $user) {
            $user->update(['role' => 'trainer']);
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

        unset($admin);
    }
}
