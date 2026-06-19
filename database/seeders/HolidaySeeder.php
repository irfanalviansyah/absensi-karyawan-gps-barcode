<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            '2026-01-01' => 'Tahun Baru Masehi',
            '2026-01-16' => 'Isra Mikraj Nabi Muhammad SAW',
            '2026-02-17' => 'Tahun Baru Imlek 2577',
            '2026-03-18' => 'Cuti Bersama Idul Fitri',
            '2026-03-19' => 'Hari Raya Nyepi',
            '2026-03-20' => 'Hari Raya Idul Fitri',
            '2026-03-21' => 'Hari Raya Idul Fitri',
            '2026-03-23' => 'Cuti Bersama Idul Fitri',
            '2026-03-24' => 'Cuti Bersama Idul Fitri',
            '2026-03-25' => 'Cuti Bersama Idul Fitri',
            '2026-04-03' => 'Wafat Isa Al-Masih',
            '2026-05-01' => 'Hari Buruh Internasional',
            '2026-05-14' => 'Kenaikan Isa Al-Masih',
            '2026-05-27' => 'Hari Raya Idul Adha',
            '2026-05-31' => 'Hari Raya Waisak',
            '2026-06-01' => 'Hari Lahir Pancasila',
            '2026-06-16' => 'Tahun Baru Islam 1448 H',
        ];

        // Filter only weekdays
        $weekdayHolidays = [];
        foreach ($holidays as $date => $name) {
            $d = Carbon::parse($date);
            if (!$d->isWeekend()) {
                $weekdayHolidays[$date] = $name;
            }
        }

        $userIds = User::where('group', 'user')->pluck('id')->toArray();
        $count = 0;

        foreach ($weekdayHolidays as $date => $name) {
            // Delete any existing records for this date
            Attendance::where('date', $date)->delete();

            $rows = [];
            foreach ($userIds as $uid) {
                $rows[] = [
                    'user_id' => $uid,
                    'date' => $date,
                    'time_in' => null,
                    'time_out' => null,
                    'status' => 'excused',
                    'note' => 'Libur Nasional: ' . $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Attendance::insertOrIgnore($chunk);
                $count += count($chunk);
            }
        }

        echo "Inserted {$count} holiday records for " . count($weekdayHolidays) . " holiday days\n";
    }
}
