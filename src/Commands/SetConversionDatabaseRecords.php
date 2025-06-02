<?php

namespace FinnWiel\ShazzooMedia\Commands;

use Illuminate\Console\Command;

class SetConversionDatabaseRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:set-db 
                        {--id= : Media ID to update (omit for all)} 
                        {--append : Add to existing conversions instead of overwriting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively set conversion(s) in the database for one media item or all.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mediaId = $this->option('id');
        $append = $this->option('append');

        $modelClass = config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);

        // Get available conversion keys from config
        $available = array_keys(config('shazzoo_media.conversions') ?? []);

        if (empty($available)) {
            $this->warn('⚠️  No conversions are defined in the config.');
            return Command::FAILURE;
        }

        $selected = $this->choice(
            'Which conversions should be applied?',
            $available,
            null,
            null,
            true // allow multiple selections
        );

        if ($mediaId) {
            $media = $modelClass::find($mediaId);

            if (! $media) {
                $this->error("❌ Media with ID {$mediaId} not found.");
                return Command::FAILURE;
            }

            $conversions = $append
                ? array_merge(json_decode($media->conversions, true) ?? [], $selected)
                : $selected;

            $media->conversions = json_encode(array_values(array_unique($conversions)));
            $media->save();

            $this->info("✅ Conversions updated for media ID {$mediaId}.");
            return Command::SUCCESS;
        }

        $modelClass::chunk(50, function ($items) use ($append, $selected) {
            foreach ($items as $media) {
                $conversions = $append
                    ? array_merge(json_decode($media->conversions, true) ?? [], $selected)
                    : $selected;

                $media->conversions = json_encode(array_values(array_unique($conversions)));
                $media->save();

                $this->line("✅ Media ID {$media->id} updated.");
            }
        });

        return Command::SUCCESS;
    }
}
