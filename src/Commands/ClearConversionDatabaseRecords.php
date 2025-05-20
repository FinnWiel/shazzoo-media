<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Console\Command;

class ClearConversionDatabaseRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:clear-db 
                        {--id= : Media ID to clear conversions. (Leave blank to clear all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear conversion(s) in the database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mediaId = $this->option('id');

        if ($mediaId) {
            $media = ShazzooMedia::find($mediaId);
            if (! $media) {
                $this->error("❌ Media with ID {$mediaId} not found.");
                return Command::FAILURE;
            }

            $media->conversions = [];
            $media->save();

            $this->info("🧹 Cleared conversions for media ID {$mediaId}.");
        } else {
            $count = ShazzooMedia::whereNotNull('conversions')->count();
            ShazzooMedia::query()->update(['conversions' => null]);
            
            $this->info("🧹 Cleared conversions for {$count} media items.");
        }

        return Command::SUCCESS;
    }
}
