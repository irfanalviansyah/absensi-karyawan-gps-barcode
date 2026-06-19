<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeAttendanceSeeder extends Seeder
{
    // Hari libur nasional 2026 (weekdays only)
    private array $holidays = [
        '2026-01-01', '2026-01-16', '2026-02-17',
        '2026-03-18', '2026-03-19', '2026-03-20', '2026-03-21',
        '2026-03-23', '2026-03-24', '2026-03-25',
        '2026-04-03', '2026-05-01', '2026-05-14',
        '2026-05-27', '2026-06-01', '2026-06-16',
    ];

    public function run(): void
    {
        // Fixed employees
        $employees = [
            [
                'name' => 'Eman Sulaeman',
                'email' => 'eman.sulaeman@chemindo.com',
            ],
            [
                'name' => 'Salsa Sahara Nur Aisha',
                'email' => 'salsa.sahara@chemindo.com',
            ],
        ];

        // Generate 298 random Indonesian names to reach 300 total
        $faker = fake('id_ID');
        $faker->seed(12345); // Fixed seed agar nama random konsisten tiap run
        $existingEmails = collect($employees)->pluck('email')->toArray();

        for ($i = 0; $i < 298; $i++) {
            $name = $faker->name();
            $emailBase = strtolower(str_replace([' ', "'", '.'], ['.', '', ''], $name));
            $email = $emailBase . '@chemindo.com';

            while (in_array($email, $existingEmails)) {
                $email = $emailBase . $faker->numerify('##') . '@chemindo.com';
            }

            $existingEmails[] = $email;
            $employees[] = [
                'name' => $name,
                'email' => $email,
            ];
        }

        $startDate = Carbon::now()->subMonths(5)->startOfDay();
        $endDate = Carbon::now()->startOfDay(); // Include today

        // Pre-calculate workdays (excluding holidays)
        $workdays = [];
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            if (!$current->isWeekend() && !in_array($dateStr, $this->holidays)) {
                $workdays[] = $dateStr;
            }
            $current->addDay();
        }

        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();

        foreach ($employees as $idx => $emp) {
            $user = User::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['name'],
                    'password' => Hash::make('password'),
                    'raw_password' => 'password',
                    'group' => 'user',
                    'phone' => $faker->phoneNumber(),
                    'gender' => $faker->randomElement(['male', 'female']),
                    'nip' => $faker->unique()->numerify('########'),
                    'address' => $faker->address(),
                    'city' => $faker->city(),
                ]
            );

            // Skip if user already has attendance (re-run safe)
            if ($user->attendances()->count() > 0) {
                continue;
            }

            $attendances = [];
            foreach ($workdays as $day) {
                // Eman: hadir hari ini
                if ($emp['email'] === 'eman.sulaeman@chemindo.com' && $day === $today) {
                    $attendances[] = [
                        'user_id' => $user->id,
                        'date' => $day,
                        'time_in' => '08:00:00',
                        'time_out' => '17:00:00',
                        'status' => 'present',
                        'note' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                // Salsa: cuti kemarin & hari ini
                if ($emp['email'] === 'salsa.sahara@chemindo.com' && in_array($day, [$yesterday, $today])) {
                    $attendances[] = [
                        'user_id' => $user->id,
                        'date' => $day,
                        'time_in' => null,
                        'time_out' => null,
                        'status' => 'excused',
                        'note' => 'Cuti',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                $record = $this->generateVariedAttendance($user->id, $day, $faker);
                if ($record) {
                    $attendances[] = $record;
                }
            }

            foreach (array_chunk($attendances, 500) as $chunk) {
                Attendance::insertOrIgnore($chunk);
            }

            // Salsa leave request
            if ($emp['email'] === 'salsa.sahara@chemindo.com') {
                $admin = User::where('group', 'superadmin')->first();
                LeaveRequest::firstOrCreate(
                    ['user_id' => $user->id, 'from_date' => $yesterday, 'to_date' => $today],
                    [
                        'type' => 'excused',
                        'note' => 'Keperluan pribadi',
                        'status' => 'approved',
                        'reviewed_by' => $admin?->id,
                        'reviewed_at' => now(),
                    ]
                );
            }
        }
    }

    private function generateVariedAttendance(string $userId, string $date, $faker): ?array
    {
        $rand = $faker->numberBetween(1, 100);

        // 5% chance absent (no record at all)
        if ($rand <= 5) {
            return null;
        }

        $timeIn = null;
        $timeOut = null;
        $status = 'present';
        $note = null;

        // 10% chance late (08:05 - 09:30)
        if ($rand <= 15) {
            $timeIn = sprintf('%02d:%02d:00', $faker->numberBetween(8, 9), $faker->numberBetween(5, 55));
            $timeOut = sprintf('%02d:%02d:00', $faker->randomElement([17, 17, 17, 18]), $faker->numberBetween(0, 30));
            $status = 'late';
        }
        // 5% chance sick
        elseif ($rand <= 20) {
            $status = 'sick';
            $note = $faker->randomElement(['Demam', 'Flu', 'Sakit kepala', 'Diare', 'Batuk']);
        }
        // 3% chance excused/izin
        elseif ($rand <= 23) {
            $status = 'excused';
            $note = $faker->randomElement(['Urusan keluarga', 'Acara pernikahan', 'Keperluan pribadi', 'Ke dokter']);
        }
        // 3% chance incomplete (masuk tapi lupa absen pulang)
        elseif ($rand <= 26) {
            $hourIn = $faker->numberBetween(7, 8);
            $minIn = $faker->numberBetween(0, 59);
            $timeIn = sprintf('%02d:%02d:00', $hourIn, $minIn);
            $status = 'incomplete';
        }
        // 74% present normal with slight time variation
        else {
            $hourIn = $faker->randomElement([7, 7, 7, 8]);
            $minIn = $hourIn === 7 ? $faker->numberBetween(30, 59) : 0;
            $timeIn = sprintf('%02d:%02d:00', $hourIn, $minIn);
            $hourOut = $faker->randomElement([17, 17, 17, 17, 18]);
            $minOut = $faker->numberBetween(0, 30);
            $timeOut = sprintf('%02d:%02d:00', $hourOut, $minOut);
            $status = 'present';
        }

        return [
            'user_id' => $userId,
            'date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
