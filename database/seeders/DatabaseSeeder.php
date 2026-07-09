<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            MembershipPackageSeeder::class,
            ExerciseSeeder::class,
            FaqCategorySeeder::class,
            UserSeeder::class,
            MembershipSeeder::class,
            TrainerScheduleSeeder::class,
            WorkoutPlanSeeder::class,
            FaqSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}
