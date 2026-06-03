<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            'Membership' => [
                ['q' => 'How do I renew my membership?', 'a' => 'You can renew via the mobile app up to 7 days before expiry.'],
                ['q' => 'Can I freeze my membership?', 'a' => 'Contact admin for freeze requests on monthly or yearly plans.'],
            ],
            'Facilities' => [
                ['q' => 'What are the operating hours?', 'a' => 'We are open daily from 06:00 to 22:00.'],
                ['q' => 'Is parking available?', 'a' => 'Yes, free parking is available for members.'],
            ],
            'Training' => [
                ['q' => 'How do I book a personal trainer?', 'a' => 'Use the trainer booking section in the mobile app.'],
            ],
            'Payments' => [
                ['q' => 'What payment methods are accepted?', 'a' => 'We accept bank transfer, cash, and QRIS.'],
            ],
        ];

        foreach ($faqs as $categoryName => $items) {
            $category = FaqCategory::query()->where('name', $categoryName)->first();
            if (! $category) {
                continue;
            }

            foreach ($items as $order => $item) {
                Faq::query()->firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'question' => $item['q'],
                    ],
                    [
                        'answer' => $item['a'],
                        'order' => $order + 1,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
