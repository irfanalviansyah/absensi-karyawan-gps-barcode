<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Database\Seeder;

class DivisionAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $divIds = Division::pluck('id', 'name');
        $jobIds = JobTitle::pluck('id', 'name');

        // Eman = SPV Marketing
        User::where('email', 'eman.sulaeman@chemindo.com')->update([
            'division_id' => $divIds['Marketing'],
            'job_title_id' => $jobIds['Supervisor'],
        ]);

        // Salsa = Staff Marketing
        User::where('email', 'salsa.sahara@chemindo.com')->update([
            'division_id' => $divIds['Marketing'],
            'job_title_id' => $jobIds['Staff'],
        ]);

        // Distribusi realistis pabrik kimia untuk sisanya
        $distribution = [
            ['div' => 'Produksi', 'weight' => 40, 'jobs' => ['Operator' => 60, 'Staff' => 15, 'Teknisi' => 10, 'Supervisor' => 10, 'Kepala Bagian' => 3, 'Manager' => 2]],
            ['div' => 'Marketing', 'weight' => 10, 'jobs' => ['Staff' => 60, 'Supervisor' => 25, 'Kepala Bagian' => 10, 'Manager' => 5]],
            ['div' => 'Quality Control', 'weight' => 12, 'jobs' => ['Analis' => 50, 'Staff' => 25, 'Supervisor' => 15, 'Kepala Bagian' => 7, 'Manager' => 3]],
            ['div' => 'Warehouse', 'weight' => 12, 'jobs' => ['Staff' => 50, 'Operator' => 30, 'Supervisor' => 12, 'Kepala Bagian' => 5, 'Manager' => 3]],
            ['div' => 'HRD & GA', 'weight' => 8, 'jobs' => ['Staff' => 40, 'Admin' => 30, 'Supervisor' => 15, 'Kepala Bagian' => 10, 'Manager' => 5]],
            ['div' => 'Finance', 'weight' => 6, 'jobs' => ['Staff' => 45, 'Admin' => 30, 'Supervisor' => 15, 'Kepala Bagian' => 7, 'Manager' => 3]],
            ['div' => 'Engineering', 'weight' => 7, 'jobs' => ['Teknisi' => 50, 'Staff' => 20, 'Supervisor' => 15, 'Kepala Bagian' => 10, 'Manager' => 5]],
            ['div' => 'R&D', 'weight' => 5, 'jobs' => ['Analis' => 45, 'Staff' => 30, 'Supervisor' => 15, 'Kepala Bagian' => 7, 'Manager' => 3]],
        ];

        // Build weighted division pool
        $divPool = [];
        foreach ($distribution as $d) {
            for ($i = 0; $i < $d['weight']; $i++) {
                $divPool[] = $d;
            }
        }

        $others = User::where('group', 'user')
            ->whereNotIn('email', ['eman.sulaeman@chemindo.com', 'salsa.sahara@chemindo.com'])
            ->whereNull('division_id')
            ->get();

        foreach ($others as $user) {
            $pick = $divPool[array_rand($divPool)];
            $divId = $divIds[$pick['div']];

            // Pick job based on weights
            $jobPool = [];
            foreach ($pick['jobs'] as $jobName => $w) {
                for ($i = 0; $i < $w; $i++) {
                    $jobPool[] = $jobName;
                }
            }
            $jobName = $jobPool[array_rand($jobPool)];
            $jobId = $jobIds[$jobName];

            $user->update(['division_id' => $divId, 'job_title_id' => $jobId]);
        }
    }
}
