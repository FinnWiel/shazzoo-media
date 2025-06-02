<?php

namespace FinnWiel\ShazzooMedia\Commands;

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
        $modelClass = config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);
        $mediaId = $this->option('id');

        if ($mediaId) {
            $media = $modelClass::find($mediaId);
            if (!$media) {
                $this->error("❌ Media with ID {$mediaId} not found.");
                return Command::FAILURE;
            }

            $media->conversions = [];
            $media->save();

            $this->info("✅ Cleared conversions for media ID {$mediaId}.");
            return Command::SUCCESS;
        }

        $count = $modelClass::query()->update(['conversions' => json_encode([])]);
        $this->info("✅ Cleared conversions for {$count} media items.");
        return Command::SUCCESS;
    }
}
