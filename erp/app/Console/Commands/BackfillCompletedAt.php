<?php

namespace App\Console\Commands;

use App\Models\Column;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCompletedAt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-completed-at';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill completed_at for tasks that are currently in completed columns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding completed columns...');
        
        $completedColumnIds = Column::whereRaw("LOWER(name) REGEXP 'done|completed|complete|closed|finish|finished'")
            ->pluck('id');
        
        if ($completedColumnIds->isEmpty()) {
            $this->info('No completed columns found.');
            return;
        }
        
        $this->info('Found ' . $completedColumnIds->count() . ' completed columns.');
        
        $tasksUpdated = Task::whereIn('column_id', $completedColumnIds)
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('created_at')]);
        
        $this->info("Updated {$tasksUpdated} tasks with completed_at timestamp.");
    }
}
