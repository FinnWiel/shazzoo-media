<?php

namespace FinnWiel\ShazzooMedia\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearMediaConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:clear-conversions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears only the conversions folders inside media subdirectories.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mediaDisk = 'public';
        $mediaPath = 'media';

        if (!Storage::disk($mediaDisk)->exists($mediaPath)) {
            $this->warn("⚠️  '{$mediaPath}/' folder does not exist.");
            return;
        }

        // Get all directories inside media/
        $directories = Storage::disk($mediaDisk)->directories($mediaPath);

        $deletedAny = false;

        foreach ($directories as $directory) {
            $conversionPath = $directory . '/conversions';
            if (Storage::disk($mediaDisk)->exists($conversionPath)) {
                Storage::disk($mediaDisk)->deleteDirectory($conversionPath);
                $this->line("🗑️  Deleted '{$conversionPath}/'");
                $deletedAny = true;
            }
        }

        if (!$deletedAny) {
            $this->info("✅ No conversions folders found to delete.");
        }
    }
}
