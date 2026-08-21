<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RepairMediaPaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:repair-paths {--dry-run : Report what would change without touching the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-points media records whose stored path no longer exists on disk at the single file in their directory.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);
        $dryRun = (bool) $this->option('dry-run');

        $repaired = 0;
        $unresolved = 0;

        foreach ($modelClass::query()->cursor() as $media) {
            $disk = Storage::disk($media->disk);

            if (blank($media->path) === false && $disk->exists($media->path)) {
                continue;
            }

            $directory = trim((string) $media->directory, '/');

            if (blank($directory)) {
                $this->warn("⚠️  #{$media->id} '{$media->path}' is missing and the record has no directory.");
                $unresolved++;

                continue;
            }

            $files = $disk->files($directory);

            if (count($files) !== 1) {
                $count = count($files);
                $this->warn("⚠️  #{$media->id} '{$media->path}' is missing and '{$directory}/' holds {$count} files — skipped.");
                $unresolved++;

                continue;
            }

            $newPath = $files[0];
            $newName = pathinfo($newPath, PATHINFO_FILENAME);
            $newExt = pathinfo($newPath, PATHINFO_EXTENSION);

            $this->line("🔧 #{$media->id} '{$media->path}' → '{$newPath}'");

            // The name column is unique, so only adopt the file's name when no other
            // record already holds it. Re-pointing path is what fixes the broken URL.
            $nameIsFree = $newName === $media->name || $modelClass::query()
                ->where('name', $newName)
                ->whereKeyNot($media->getKey())
                ->doesntExist();

            if (! $nameIsFree) {
                $this->warn("   name '{$newName}' is taken by another record — only the path is repaired.");
            }

            if ($dryRun) {
                $repaired++;

                continue;
            }

            $media->path = $newPath;

            if ($nameIsFree) {
                $media->name = $newName;
                $media->ext = $newExt;
            }

            // Quietly: the observer's rename branch would otherwise try to move
            // files around again on the back of this repair.
            $media->saveQuietly();

            $repaired++;
        }

        if ($repaired === 0 && $unresolved === 0) {
            $this->info('✅ No broken media paths found.');

            return self::SUCCESS;
        }

        $verb = $dryRun ? 'would be repaired' : 'repaired';
        $this->newLine();
        $this->info("✅ {$repaired} record(s) {$verb}, {$unresolved} left unresolved.");

        return self::SUCCESS;
    }
}
