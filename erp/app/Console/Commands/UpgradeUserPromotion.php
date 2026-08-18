<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\EmployeePromotion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpgradeUserPromotion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:upgrade-user-promotion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $promotions = EmployeePromotion::where('status', 'approved')
            ->whereDate('effective_from', '<=', today())
            ->get();

        foreach ($promotions as $promotion) {

            $existingOccupant = EmployeeProfile::where('department_id', $promotion->new_department_id)
                ->where('designation_id', $promotion->new_designation_id)
                ->where('user_id', '!=', $promotion->user_id)
                ->exists();

            if ($existingOccupant) {
                $promotion->update([
                    'status' => 'rejected',
                    'reson' => "Skipped promotion ID {$promotion->id}: Position already occupied.",
                ]);
                continue;
            }

            DB::transaction(function () use ($promotion) {

                // Update promotion
                // $promotion->update([
                //     'status' => 'approved',
                //     'approved_at' => now(),
                // ]);

                // Update Profile
                $profile = EmployeeProfile::where('user_id', $promotion->user_id)->lockForUpdate()->first();
                if ($profile) {                    
                    $profile->update([
                        'designation_id' => $promotion->new_designation_id,
                        'salary' => $promotion->new_salary,
                    ]);
                } else{
                    EmployeeProfile::create([
                        'user_id'         => $promotion->user_id,
                        'department_id'   => $promotion->new_department_id,
                        'designation_id'  => $promotion->new_designation_id,
                        'joining_date'    => $promotion->effective_from,
                        'salary'          => $promotion->new_salary,
                        'employment_type' => 'full_time',
                    ]);
                }
            });
        }
    }
}
