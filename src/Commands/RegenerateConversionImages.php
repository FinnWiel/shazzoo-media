<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory;
use FinnWiel\ShazzooMedia\Models\MediaExtended;
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
    public function handle()
    {
        $only = $this->option('only');
        $id = $this->option('id');

        if ($id) {
            $images = MediaExtended::where('id', $id)->get();
            $this->info("🔁 Regenerating conversions for image ID: {$id}...");
        } else {
            $images = MediaExtended::all();
            $this->info('🔁 Regenerating conversions for all images...');
        }

        $this->output->progressStart($images->count());

        foreach ($images as $image) {
            $this->regenerateImageConversions($image, $only);
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $this->info('✅ Image conversion regeneration complete.');
        return Command::SUCCESS;
    }

    protected function regenerateImageConversions(MediaExtended $image, ?string $only = null)
    {
        $imagePath = storage_path('app/public/' . $image->path);

        if (!file_exists($imagePath)) {
            return;
        }

        $ext = strtolower($image->ext);
        if (in_array($ext, ['svg', 'pdf'])) {
            return;
        }

        $conversions = json_decode($image->conversions, true) ?? [];
        if (empty($conversions)) {
            return;
        }

        foreach ($conversions as $conversion) {
            if ($only && $conversion !== $only) {
                continue;
            }

            $config = config('shazzoo_media.conversions.' . $conversion);
            if (!$config) {
                $this->warn(" ⚠️  Conversion '{$conversion}' is not defined in the config.");
                continue;
            }

            // Build expected output path
            $basePath = public_path("storage/conversions/{$image->name}/{$image->name}-{$conversion}");
            $matchingFiles = File::glob("{$basePath}.*");

            $outputPath = $matchingFiles[0] ?? null;

            // Delete existing file if it exists
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }

            try {
                // Regenerate the image
                $this->server->makeImage($image->path, [
                    'conversion' => $conversion,
                    'w' => $config['width'],
                    'h' => $config['height'],
                    'fm' => config('shazzoo_media.conversion_ext'),
                ]);
            } catch (\Exception $e) {
                $this->error("❌ Error regenerating {$conversion} for {$image->name}: " . $e->getMessage());
            }
        }
    }
}
