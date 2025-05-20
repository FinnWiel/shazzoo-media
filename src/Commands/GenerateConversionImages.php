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
        $imagePath = storage_path('app/public/' . $image->path);

        if (!file_exists($imagePath)) {
            return;
        }

        $ext = strtolower($image->ext);

        if (in_array($ext, ['svg', 'pdf'])) {
            return; // Skip SVG and PDF files
        }

        $conversions = json_decode($image->conversions, true) ?? [];

        if (empty($conversions)) {
            return; // No conversions to generate
        }

        foreach ($conversions as $conversion) {
            // Skip conversions if --only is used
            if ($only && $conversion !== $only) {
                continue;
            }

            $conversionConfig = config('shazzoo_media.conversions.' . $conversion);
            $defaultFit = config('shazzoo_media.fit', 'max');
            $defaultFormat = config('shazzoo_media.conversion_ext', 'webp');

            if (!$conversionConfig) {
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
            } catch (\Exception $e) {
                //
            }
        }
    }
}
