<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Membership', 'description' => 'Questions about packages and renewals', 'order' => 1],
            ['name' => 'Facilities', 'description' => 'Gym equipment and amenities', 'order' => 2],
            ['name' => 'Training', 'description' => 'Personal training and programs', 'order' => 3],
            ['name' => 'Payments', 'description' => 'Billing and payment methods', 'order' => 4],
            ['name' => 'General', 'description' => 'Other common questions', 'order' => 5],
        ];

        foreach ($categories as $category) {
            FaqCategory::query()->firstOrCreate(
                ['name' => $category['name']],
                array_merge($category, ['status' => 'active'])
            );
        }
    }
}
