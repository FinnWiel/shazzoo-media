<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
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
    public function handle()
    {
        $only = $this->option('only');

        if ($this->option('all')) {
            $images = ShazzooMedia::all();
            $this->info('Starting conversion for all images...');
        } elseif ($imageId = $this->option('id')) {
            $images = ShazzooMedia::where('id', $imageId)->get();
            $this->info("Starting conversion for image id: {$imageId}...");
        } else {
            $this->error('You must specify either --all or --id.');
            return 1;
        }

        $this->output->progressStart($images->count());

        foreach ($images as $image) {
            $this->generateImageCache($image, $only);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('Image conversions complete!');
    }

    /**
     * Generate the image cache using Glide.
     *
     * @param  App\Models\ShazzooMedia $image
     * @param  string|null $only
     * @return void
     */
    protected function generateImageCache(ShazzooMedia $image, ?string $only = null)
    {
        if (empty($image->path)) {
            logger()->warning("Skipping media ID {$image->id}: path is missing.");
            return;
        }

        $imagePath = storage_path('app/public/' . $image->path);
        logger()->info("Processing media ID {$image->id}: path {$image->path}");

        if (!file_exists($imagePath)) {
            logger()->warning("File does not exist for media ID {$image->id}: {$imagePath}");
            return;
        }

        $ext = strtolower($image->ext);

        if (in_array($ext, ['svg', 'pdf'])) {
            logger()->info("Skipping unsupported format '{$ext}' for media ID {$image->id}");
            return;
        }

        $conversions = json_decode($image->conversions, true) ?? [];

        if (empty($conversions)) {
            logger()->info("No conversions found for media ID {$image->id}");
            return;
        }

        foreach ($conversions as $conversion) {
            if ($only && $conversion !== $only) {
                continue;
            }

            $conversionConfig = config('shazzoo_media.conversions.' . $conversion);
            $defaultFit = config('shazzoo_media.fit', 'max');
            $defaultFormat = config('shazzoo_media.conversion_ext', 'webp');

            if (!$conversionConfig) {
                logger()->warning("Conversion config missing for '{$conversion}'");
                continue;
            }

            try {
                $this->server->makeImage($image->path, [
                    'conversion' => $conversion,
                    'w' => $conversionConfig['width'] ?? null,
                    'h' => $conversionConfig['height'] ?? null,
                    'fit' => $conversionConfig['fit'] ?? $defaultFit,
                    'fm' => $conversionConfig['ext'] ?? $defaultFormat,
                ]);

                logger()->info("Generated conversion '{$conversion}' for media ID {$image->id}");
            } catch (\Exception $e) {
                logger()->error("Failed to generate conversion '{$conversion}' for media ID {$image->id}: " . $e->getMessage());
            }
        }
    }
}
