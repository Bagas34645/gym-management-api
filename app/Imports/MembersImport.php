<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MembersImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    public array $errorRows = [];

    public function __construct(private bool $overrideExisting = false) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $email = $row['email'] ?? null;
            $phone = $row['phone'] ?? null;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errorRows[] = ['row' => $rowNum, 'message' => 'Email invalid'];

                continue;
            }

            $existing = User::query()->where('email', $email)->first();

            if ($existing && ! $this->overrideExisting) {
                $this->skipped++;

                continue;
            }

            try {
                User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $row['name'] ?? 'Imported Member',
                        'phone' => $phone ?? '08'.substr(md5($email), 0, 10),
                        'password' => bcrypt($row['password'] ?? 'password123'),
                        'role' => 'member',
                        'status' => 'active',
                    ],
                );
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errorRows[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }
    }
}
