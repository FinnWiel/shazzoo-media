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
        $conversions = json_decode($media->conversions, true) ?? [];

        $basePath = "conversions/{$media->name}";
        if (File::exists(storage_path("app/public/{$basePath}"))) {
            File::deleteDirectory(storage_path("app/public/{$basePath}"));
            $this->line("🗑️  Deleted old conversions in '{$basePath}'");
        }

        if ($only && in_array($only, $conversions)) {
            $this->server->getImageResponse($media->path, ['p' => $only]);
            return;
        }

        foreach ($conversions as $conversion) {
            $this->server->getImageResponse($media->path, ['p' => $conversion]);
        }
    }
}
