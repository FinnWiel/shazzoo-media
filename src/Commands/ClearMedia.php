<?php

namespace FinnWiel\ShazzooMedia\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears your images from the storage folder.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mediaDisk = 'public';
        $mediaPath = 'media';
        $conversionPath = 'conversions';

        // Delete all files in media/
        if (Storage::disk($mediaDisk)->exists($mediaPath)) {
            Storage::disk($mediaDisk)->deleteDirectory($mediaPath);
            $this->line("🗑️  Deleted all files in '{$mediaPath}/'");
        } else {
            $this->warn("⚠️  '{$mediaPath}/' folder does not exist.");
        }

        // Delete all files in conversions/
        if (Storage::disk($mediaDisk)->exists($conversionPath)) {
            Storage::disk($mediaDisk)->deleteDirectory($conversionPath);
            $this->line("🗑️  Deleted all files in '{$conversionPath}/'");
        } else {
            $this->warn("⚠️  '{$conversionPath}/' folder does not exist.");
        }
    }
}
