<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RegenerateConversionImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:regenerate
                        {--id= : The ID of a single media item to regenerate}
                        {--only= : Only regenerate a specific conversion (e.g., thumbnail, medium)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate image conversions (delete and recreate)';

    protected $server;

    public function __construct()
    {
        parent::__construct();
        $this->server = app(ShazzooMediaServerFactory::class)->getFactory();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);
        $id = $this->option('id');
        $only = $this->option('only');

        if ($id) {
            $media = $modelClass::find($id);

            if (! $media) {
                $this->error("❌ Media with ID {$id} not found.");
                return Command::FAILURE;
            }

            $this->regenerate($media, $only);
            $this->info("✅ Regenerated conversions for media ID {$id}.");
            return Command::SUCCESS;
        }

        $this->warn('⚠️  Please provide an --id. Bulk regeneration is not supported in this command.');
        return Command::FAILURE;
    }

    protected function regenerate($media, $only = null): void
    {
        if (!str_starts_with($media->type, 'image/')) {
            $this->warn("⚠️  Skipping media ID {$media->id} (MIME: {$media->type}) not an image.");
            return;
        }

        $conversions = json_decode($media->conversions, true) ?? [];

        if (empty($conversions)) {
            $this->warn("⚠️  No conversions found for media ID {$media->id}");
            return;
        }

        // Delete existing conversions
        $basePath = "media/{$media->id}/conversions";
        if (File::exists(storage_path("app/public/{$basePath}"))) {
            File::deleteDirectory(storage_path("app/public/{$basePath}"));
            $this->line("🗑️  Deleted old conversions in '{$basePath}'");
        }

        foreach ($conversions as $conversion) {
            // Skip conversions if --only is used
            if ($only && $conversion !== $only) {
                continue;
            }

            $conversionConfig = config("shazzoo_media.conversions.{$conversion}", []);
            $defaultFit = config('shazzoo_media.fit', 'max');
            $defaultFormat = config('shazzoo_media.conversion_ext', 'webp');

            if (empty($conversionConfig)) {
                $this->warn("⚠️  No conversion config found for '{$conversion}'");
                continue;
            }

            try {
                $this->server->makeImage($media->path, [
                    'conversion' => $conversion,
                    'w' => $conversionConfig['width'] ?? null,
                    'h' => $conversionConfig['height'] ?? null,
                    'fit' => $conversionConfig['fit'] ?? $defaultFit,
                    'fm' => $conversionConfig['ext'] ?? $defaultFormat,
                ]);
            } catch (\Exception $e) {
                $this->warn("⚠️  Failed to regenerate conversion '{$conversion}' for media ID {$media->id}: " . $e->getMessage());
            }
        }
    }
}
