<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Feedback;
use App\Models\Membership;
use App\Models\MembershipPackage;
use App\Models\MembershipRenewal;
use App\Models\NotificationPreference;
use App\Models\PaymentRecord;
use App\Models\Trainer;
use App\Models\TrainerBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    private const MEMBER_COUNT = 100;

    private const ATTENDANCE_DAYS = 90;

    private const PAYMENT_HISTORY_DAYS = 365;

    /** @var array<string, MembershipPackage> */
    private array $packages = [];

    /** @var list<string> */
    private array $paymentMethods = ['transfer', 'cash', 'qris', 'midtrans'];

    public function run(): void
    {
        $this->packages = MembershipPackage::query()->get()->keyBy('type')->all();

        if ($this->packages === []) {
            $this->command?->warn('Membership packages not found. Run MembershipPackageSeeder first.');

            return;
        }

        $this->command?->info('Seeding dummy members...');
        $newMembers = $this->seedMembers();

        $this->command?->info('Backdating existing members...');
        $this->backdateExistingMembers();

        $allMembers = User::query()
            ->where('role', 'member')
            ->whereDoesntHave('trainer')
            ->get();

        $this->command?->info('Seeding memberships...');
        $memberships = $this->seedMemberships($allMembers);

        $this->command?->info('Seeding payment records...');
        $this->seedPayments($memberships);

        $this->command?->info('Seeding attendance records...');
        $this->seedAttendance($allMembers);

        $this->command?->info('Seeding trainer bookings...');
        $this->seedTrainerBookings($allMembers);

        $this->command?->info('Seeding membership renewals...');
        $this->seedRenewals($memberships);

        $this->command?->info('Seeding feedback...');
        $this->seedFeedback($allMembers);

        $this->command?->info('Dummy data seeding complete.');
    }

    /**
     * @return list<User>
     */
    private function seedMembers(): array
    {
        $members = [];

        for ($i = 0; $i < self::MEMBER_COUNT; $i++) {
            $joinedAt = now()
                ->subDays(fake()->numberBetween(1, self::PAYMENT_HISTORY_DAYS))
                ->setTime(fake()->numberBetween(8, 20), fake()->numberBetween(0, 59));

            $member = User::factory()->create([
                'role' => 'member',
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ]);

            NotificationPreference::query()->firstOrCreate(
                ['user_id' => $member->id],
                ['workout_reminder_days' => ['monday', 'wednesday', 'friday']]
            );

            $members[] = $member;
        }

        return $members;
    }

    private function backdateExistingMembers(): void
    {
        User::query()
            ->where('role', 'member')
            ->whereDoesntHave('trainer')
            ->where('created_at', '>=', now()->startOfDay())
            ->each(function (User $member) {
                $joinedAt = now()->subDays(fake()->numberBetween(7, self::PAYMENT_HISTORY_DAYS));
                $member->timestamps = false;
                $member->update([
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ]);
                $member->timestamps = true;
            });
    }

    /**
     * @param  Collection<int, User>  $members
     * @return list<Membership>
     */
    private function seedMemberships($members): array
    {
        $created = [];
        $packageWeights = [
            'monthly' => 50,
            'weekly' => 25,
            'yearly' => 15,
            'daily' => 10,
        ];

        foreach ($members as $index => $member) {
            if ($member->activeMembership()->exists()) {
                $existing = $member->memberships()->latest()->first();
                if ($existing) {
                    $created[] = $existing;
                }

                continue;
            }

            $packageType = $this->weightedRandom($packageWeights);
            $package = $this->packages[$packageType] ?? reset($this->packages);

            $statusRoll = fake()->numberBetween(1, 100);
            $status = match (true) {
                $statusRoll <= 65 => 'active',
                $statusRoll <= 85 => 'expired',
                default => 'active',
            };

            $startDate = Carbon::parse($member->created_at)->addDays(fake()->numberBetween(0, 14));

            if ($status === 'active' && $statusRoll > 85) {
                $endDate = now()->addDays(fake()->numberBetween(1, 7));
            } elseif ($status === 'expired') {
                $endDate = now()->subDays(fake()->numberBetween(1, 60));
                $startDate = $endDate->copy()->subDays($package->duration_days);
            } else {
                $endDate = $startDate->copy()->addDays($package->duration_days);
                if ($endDate->isPast()) {
                    $endDate = now()->addDays(fake()->numberBetween(10, $package->duration_days));
                    $startDate = $endDate->copy()->subDays($package->duration_days);
                }
            }

            $paymentMethod = fake()->randomElement($this->paymentMethods);

            $membership = Membership::query()->create([
                'user_id' => $member->id,
                'package_id' => $package->id,
                'status' => $status,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'payment_method' => $paymentMethod,
                'payment_status' => 'completed',
            ]);

            $created[] = $membership;

            if ($status === 'expired' && fake()->boolean(40)) {
                $renewalStart = $endDate->copy()->addDay();
                $renewalEnd = $renewalStart->copy()->addDays($package->duration_days);

                $created[] = Membership::query()->create([
                    'user_id' => $member->id,
                    'package_id' => $package->id,
                    'status' => $renewalEnd->isFuture() ? 'active' : 'expired',
                    'start_date' => $renewalStart->toDateString(),
                    'end_date' => $renewalEnd->toDateString(),
                    'payment_method' => fake()->randomElement($this->paymentMethods),
                    'payment_status' => 'completed',
                ]);
            }
        }

        return $created;
    }

    /**
     * @param  list<Membership>  $memberships
     */
    private function seedPayments(array $memberships): void
    {
        $referenceCounter = PaymentRecord::query()->count();
        $payments = [];

        foreach ($memberships as $membership) {
            $package = $membership->package ?? MembershipPackage::query()->find($membership->package_id);
            if (! $package) {
                continue;
            }

            $paymentDate = Carbon::parse($membership->start_date);
            $payments[] = $this->buildPaymentRow($membership, $package->price, $membership->payment_method, $paymentDate, ++$referenceCounter);

            if (fake()->boolean(25)) {
                $extraDate = $paymentDate->copy()->addDays(fake()->numberBetween(15, 60));
                if ($extraDate->isPast()) {
                    $payments[] = $this->buildPaymentRow(
                        $membership,
                        $package->price,
                        fake()->randomElement($this->paymentMethods),
                        $extraDate,
                        ++$referenceCounter
                    );
                }
            }
        }

        for ($day = self::PAYMENT_HISTORY_DAYS; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $dailyCount = $this->dailyPaymentVolume($day);

            for ($i = 0; $i < $dailyCount; $i++) {
                $package = $this->packages[fake()->randomElement(['monthly', 'weekly', 'daily', 'yearly'])];
                $member = User::query()
                    ->where('role', 'member')
                    ->whereDoesntHave('trainer')
                    ->inRandomOrder()
                    ->first();

                if (! $member) {
                    continue;
                }

                $membership = Membership::query()
                    ->where('user_id', $member->id)
                    ->inRandomOrder()
                    ->first();

                $payments[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $member->id,
                    'membership_id' => $membership?->id,
                    'renewal_id' => null,
                    'amount' => $package->price,
                    'payment_method' => fake()->randomElement($this->paymentMethods),
                    'payment_date' => $date->toDateString(),
                    'reference_number' => $this->referenceNumber($date, ++$referenceCounter),
                    'status' => fake()->randomElement(['completed', 'completed', 'completed', 'pending', 'failed']),
                    'notes' => null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }
        }

        foreach (array_chunk($payments, 500) as $chunk) {
            PaymentRecord::query()->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function seedAttendance($members): void
    {
        $activeMemberIds = Membership::query()
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique()
            ->all();

        $records = [];
        $peakHours = [6, 7, 8, 17, 18, 19];

        for ($day = self::ATTENDANCE_DAYS; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $isWeekend = $date->isWeekend();
            $baseVisits = $isWeekend
                ? fake()->numberBetween(15, 35)
                : fake()->numberBetween(25, 55);

            if ($day === 0) {
                $baseVisits = fake()->numberBetween(20, 45);
            }

            $visitingMembers = $members
                ->filter(fn (User $m) => in_array($m->id, $activeMemberIds, true) || fake()->boolean(20))
                ->shuffle()
                ->take(min($baseVisits, $members->count()));

            foreach ($visitingMembers as $member) {
                $hour = fake()->randomElement($peakHours);
                $checkIn = $date->copy()->setTime($hour, fake()->numberBetween(0, 59));
                $duration = fake()->numberBetween(45, 120);

                $records[] = [
                    'id' => Str::uuid()->toString(),
                    'user_id' => $member->id,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $checkIn->copy()->addMinutes($duration),
                    'location' => fake()->randomElement(['Main Entrance', 'Side Entrance', 'VIP Entrance']),
                    'face_match_confidence' => fake()->randomFloat(2, 0.78, 0.99),
                    'verification_status' => fake()->randomElement(['verified', 'verified', 'verified', 'manual_verified']),
                    'verified_by' => null,
                    'verified_at' => null,
                    'notes' => null,
                    'created_at' => $checkIn,
                ];
            }
        }

        foreach (array_chunk($records, 500) as $chunk) {
            AttendanceRecord::query()->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function seedTrainerBookings($members): void
    {
        $trainers = Trainer::query()->with('schedules')->get()->filter(
            fn (Trainer $t) => $t->schedules->isNotEmpty()
        );

        if ($trainers->isEmpty()) {
            return;
        }

        $bookedSlots = TrainerBooking::query()
            ->get(['trainer_id', 'schedule_id', 'session_date'])
            ->mapWithKeys(function (TrainerBooking $booking) {
                $date = Carbon::parse($booking->session_date)->toDateString();

                return ["{$booking->trainer_id}-{$booking->schedule_id}-{$date}" => true];
            })
            ->all();

        foreach ($members->shuffle()->take(40) as $member) {
            $sessionCount = fake()->numberBetween(2, 8);
            $created = 0;
            $attempts = 0;
            $maxAttempts = $sessionCount * 15;

            while ($created < $sessionCount && $attempts < $maxAttempts) {
                $attempts++;

                $trainer = $trainers->random();
                $schedule = $trainer->schedules->random();
                $daysAgo = fake()->numberBetween(-14, 90);
                $sessionDate = now()->subDays($daysAgo)->toDateString();
                $slotKey = "{$trainer->id}-{$schedule->id}-{$sessionDate}";

                if (isset($bookedSlots[$slotKey])) {
                    continue;
                }

                $bookedSlots[$slotKey] = true;

                $isPast = $daysAgo > 0;
                $status = match (true) {
                    ! $isPast => 'confirmed',
                    fake()->boolean(10) => 'cancelled',
                    fake()->boolean(5) => 'no_show',
                    default => 'completed',
                };

                TrainerBooking::query()->create([
                    'user_id' => $member->id,
                    'trainer_id' => $trainer->id,
                    'schedule_id' => $schedule->id,
                    'session_date' => $sessionDate,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'status' => $status,
                    'rating' => $status === 'completed' ? fake()->numberBetween(3, 5) : null,
                    'feedback' => $status === 'completed' && fake()->boolean(60)
                        ? fake()->sentence()
                        : null,
                    'cancelled_at' => $status === 'cancelled' ? $sessionDate : null,
                ]);

                $created++;
            }
        }
    }

    /**
     * @param  list<Membership>  $memberships
     */
    private function seedRenewals(array $memberships): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $activeMemberships = collect($memberships)->filter(fn (Membership $m) => $m->status === 'active');

        foreach ($activeMemberships->shuffle()->take(8) as $membership) {
            $package = $membership->package ?? MembershipPackage::query()->find($membership->package_id);
            if (! $package) {
                continue;
            }

            $status = fake()->randomElement([
                'pending_verification',
                'pending_verification',
                'pending_payment',
                'approved',
                'rejected',
            ]);

            $renewal = MembershipRenewal::query()->create([
                'membership_id' => $membership->id,
                'user_id' => $membership->user_id,
                'package_id' => $package->id,
                'previous_end_date' => $membership->end_date,
                'new_end_date' => $membership->end_date->copy()->addDays($package->duration_days),
                'status' => $status,
                'payment_method' => fake()->randomElement($this->paymentMethods),
                'amount_paid' => $package->price,
                'verified_by' => in_array($status, ['approved', 'rejected'], true) ? $admin?->id : null,
                'verified_at' => in_array($status, ['approved', 'rejected'], true) ? now()->subDays(fake()->numberBetween(1, 10)) : null,
            ]);

            if ($status === 'approved') {
                PaymentRecord::query()->create([
                    'user_id' => $membership->user_id,
                    'membership_id' => $membership->id,
                    'renewal_id' => $renewal->id,
                    'amount' => $package->price,
                    'payment_method' => $renewal->payment_method,
                    'payment_date' => now()->subDays(fake()->numberBetween(1, 30))->toDateString(),
                    'reference_number' => $this->referenceNumber(now(), PaymentRecord::query()->count() + 1),
                    'status' => 'completed',
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function seedFeedback($members): void
    {
        foreach ($members->shuffle()->take(35) as $member) {
            Feedback::query()->create([
                'user_id' => $member->id,
                'rating' => fake()->numberBetween(2, 5),
                'category' => fake()->randomElement(['facility', 'trainer', 'service', 'cleanliness', 'other']),
                'message' => fake()->paragraph(),
                'is_anonymous' => fake()->boolean(20),
                'status' => fake()->randomElement(['new', 'new', 'reviewed', 'resolved']),
                'submitted_at' => now()->subDays(fake()->numberBetween(1, 60)),
            ]);
        }
    }

    private function buildPaymentRow(
        Membership $membership,
        float $amount,
        string $method,
        Carbon $date,
        int $counter
    ): array {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => $membership->user_id,
            'membership_id' => $membership->id,
            'renewal_id' => null,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => $date->toDateString(),
            'reference_number' => $this->referenceNumber($date, $counter),
            'status' => 'completed',
            'notes' => null,
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    private function referenceNumber(Carbon $date, int $counter): string
    {
        return sprintf('REF-%s-%06d', $date->format('Ymd'), $counter);
    }

    private function dailyPaymentVolume(int $daysAgo): int
    {
        $base = max(1, (int) round(3 + (self::PAYMENT_HISTORY_DAYS - $daysAgo) / 40));

        return fake()->numberBetween(max(1, $base - 2), $base + 3);
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weightedRandom(array $weights): string
    {
        $total = array_sum($weights);
        $roll = fake()->numberBetween(1, $total);
        $cumulative = 0;

        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
