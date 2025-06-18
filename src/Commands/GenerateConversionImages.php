<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory;
use Illuminate\Console\Command;

class GenerateConversionImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:generate
                        {--id= : The ID of a single media record to convert}
                        {--all : Generate conversions for all media records}
                        {--only= : Only generate a specific conversion (e.g., thumbnail, profile)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate image cache for images';

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
        $only = $this->option('only');

        if ($id = $this->option('id')) {
            $media = $modelClass::find($id);
            if (! $media) {
                $this->error("❌ Media with ID {$id} not found.");
                return Command::FAILURE;
            }

            $this->generateConversions($media, $only);
            $this->info("✅ Conversions generated for media ID {$id}.");
            return Command::SUCCESS;
        }

        if ($this->option('all')) {
            $this->line("🔄 Generating conversions for all media records...");
            $modelClass::chunk(50, function ($items) use ($only) {
                foreach ($items as $media) {
                    $this->generateConversions($media, $only);
                    $this->line("✅ Media ID {$media->id}");
                }
            });
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Please provide either --id or --all.");
        return Command::FAILURE;
    }

    protected function generateConversions($media, $only = null): void
    {
        if (!str_starts_with($media->type, 'image/')) {
            $this->warn("⚠️  Skipping media ID {$media->id} (MIME: {$media->type}) not an image.");
            return;
        }

        $conversions = json_decode($media->conversions, true) ?? [];

        if (empty($conversions)) {
            return;
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
                $this->warn("⚠️  Failed to generate conversion '{$conversion}' for media ID {$media->id}: " . $e->getMessage());
            }
        }
    }
}
