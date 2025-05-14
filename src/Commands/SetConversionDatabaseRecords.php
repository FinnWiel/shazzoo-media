<?php

namespace FinnWiel\ShazzooMedia\Commands;

use FinnWiel\ShazzooMedia\Models\MediaExtended;
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

        // Get available conversion keys from config
        $available = array_keys(config('shazzoo_media.conversions') ?? []);

        if (empty($available)) {
            $this->error('❌ No conversions are defined in config/shazzoo_media.php.');
            return Command::FAILURE;
        }

        // Prompt user with checkbox list
        $selected = $this->choice(
            '❓ Which conversions do you want to set?',
            $available,
            multiple: true
        );

        if (empty($selected)) {
            $this->warn('⚠️ No conversions selected. Aborting.');
            return Command::SUCCESS;
        }

        if ($mediaId) {
            $media = MediaExtended::find($mediaId);

            if (! $media) {
                $this->error("❌ Media with ID {$mediaId} not found.");
                return Command::FAILURE;
            }

            $this->setConversions($media, $selected, $append);
            $this->info("✅ Set conversions for media ID {$mediaId}: " . implode(', ', $selected));
        } else {
            $allMedia = MediaExtended::all();

            foreach ($allMedia as $media) {
                $this->setConversions($media, $selected, $append);
            }

            $this->info("✅ Set conversions for all media: " . implode(', ', $selected));
        }

        return Command::SUCCESS;
    }

    protected function setConversions(MediaExtended $media, array $newConversions, bool $append): void
    {
        // Decode existing conversions safely
        $existing = json_decode($media->conversions ?? '[]', true) ?? [];

        $media->conversions = $append
            ? json_encode(array_values(array_unique(array_merge($existing, $newConversions))))
            : json_encode($newConversions);

        $media->save();
    }
}
