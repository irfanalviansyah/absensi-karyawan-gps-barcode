<?php

namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Division;
use App\Models\Education;
use App\Models\JobTitle;
use App\Models\Shift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Divisions - pabrik kimia
        $divisions = ['Produksi', 'Marketing', 'Quality Control', 'Warehouse', 'HRD & GA', 'Finance', 'Engineering', 'R&D'];
        foreach ($divisions as $value) {
            Division::firstOrCreate(['name' => $value]);
        }

        // Education levels
        $educations = ['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];
        foreach ($educations as $value) {
            Education::firstOrCreate(['name' => $value]);
        }

        // Job titles - pabrik
        $jobTitles = ['Staff', 'Supervisor', 'Kepala Bagian', 'Manager', 'Operator', 'Teknisi', 'Admin', 'Analis'];
        foreach ($jobTitles as $value) {
            JobTitle::firstOrCreate(['name' => $value]);
        }

        // Shifts
        Shift::firstOrCreate(['name' => 'Shift 1'], ['start_time' => '08:00:00', 'end_time' => '17:00:00']);
        Shift::firstOrCreate(['name' => 'Shift 2'], ['start_time' => '19:00:00', 'end_time' => '02:00:00']);

        // Barcode
        Barcode::firstOrCreate(['name' => 'Barcode 1'], [
            'value' => 'BARCODE-001',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius' => 100,
        ]);

        // Admin users
        $this->call(AdminSeeder::class);

        // 300 employees with attendance data
        $this->call(EmployeeAttendanceSeeder::class);

        // Assign divisions & job titles
        $this->call(DivisionAssignmentSeeder::class);

        // Public holidays
        $this->call(HolidaySeeder::class);
    }
}
