<?php

namespace FinnWiel\ShazzooMedia\Commands;

use Illuminate\Console\Command;

class ListConversionDefinitions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:conversions:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all image conversion definitions from shazzoo_media config';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $conversions = config('shazzoo_media.conversions', []);

        if (empty($conversions)) {
            $this->warn("⚠️  No conversions are currently defined in shazzoo_media.php.");
            return Command::SUCCESS;
        }

        $data = [];

        foreach ($conversions as $name => $settings) {
            $data[] = [
                'Name'   => $name,
                'Width'  => $settings['width'] ?? '-',
                'Height' => $settings['height'] ?? '-',
                'Fit'    => $settings['fit'] ?? config('shazzoo_media.fit', 'max'),
            ];
        }

        $this->table(['Conversion', 'Width', 'Height', 'Fit'], $data);

        return Command::SUCCESS;
    }
}
