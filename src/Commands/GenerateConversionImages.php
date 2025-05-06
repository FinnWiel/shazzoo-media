<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Glide\CustomServerFactory;
use FinnWiel\ShazzooMedia\Models\MediaExtended;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateConversionImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:generate {--id=} {--all} {--only=}';

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
        $this->server = app(CustomServerFactory::class)->getFactory();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $only = $this->option('only');

        if ($this->option('all')) {
            $images = MediaExtended::all();
            $this->info('Starting conversion for all images...');
        } elseif ($imageId = $this->option('id')) {
            $images = MediaExtended::where('id', $imageId)->get();
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
     * @param  App\Models\MediaExtended $image
     * @param  string|null $only
     * @return void
     */
    protected function generateImageCache(MediaExtended $image, ?string $only = null)
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

            if (!$conversionConfig) {
                continue;
            }

            try {
                $this->server->makeImage($image->path, [
                    'conversion' => $conversion,
                    'w' => $conversionConfig['width'],
                    'h' => $conversionConfig['height'],
                    'fm' => config('shazzoo_media.default_extension'),
                    'fit' => config('shazzoo_media.fit', 'max'),
                ]);
                Log::info("Conversion {$conversion} was successfully generated for: {$image->name}.");
            } catch (\Exception $e) {
                Log::error("Failed to generate {$conversion} for {$image->name}: " . $e->getMessage());
            }
        }
    }
}
