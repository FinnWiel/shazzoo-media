<?php

namespace FinnWiel\ShazzooMedia;

use Awcodes\Curator\Models\Media;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use FinnWiel\ShazzooMedia\Commands\ClearConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Commands\ClearMedia;
use FinnWiel\ShazzooMedia\Commands\GenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\ListConversionDefinitions;
use FinnWiel\ShazzooMedia\Commands\RegenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\SetConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Components\Modals\ShazzooMediaPanel;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Observers\ShazzooMediaObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ShazzooMediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('shazzoo_media')
            ->hasConfigFile()
            ->hasViews('resources/views')
            ->hasMigrations([
                'create_media_table',
            ])
            ->hasTranslations()
            ->hasCommands([
                ClearConversionDatabaseRecords::class,
                ClearMedia::class,
                GenerateConversionImages::class,
                ListConversionDefinitions::class,
                RegenerateConversionImages::class,
                SetConversionDatabaseRecords::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted(): void
    {
        $modelClass = config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);
        // Use new views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'curator');
        // $this->loadViewsFrom(__DIR__ . '/../resources/views/vendor/curator', 'curator');
        $this->publishes([
            __DIR__ . '/Policies/MediaPolicy.php.stub' => app_path('Policies/MediaPolicy.php'),
        ], 'shazzoo-media-policy');

        $this->publishes([
            __DIR__ . '/Models/ShazzooMedia.php.stub' => app_path('Models/ShazzooMedia.php'),
        ], 'shazzoo-media-model');

        // Set all changes for curator conifig to work with shazzoo media
        config()->set('curator.resources.resource', \FinnWiel\ShazzooMedia\Resources\MediaResource::class); // Resource
        config()->set('curator.model', $modelClass); // Model
        config()->set('curator.glide.server', \FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory::class);
        config()->set('curator.glide.route_path', 'storage'); // Glide server
        config()->set('curator.tabs.display_curation', false); // Display curation tab
        config()->set('curator.tabs.display_upload_new', false); // Display upload new tab
        config()->set('curator.multi_select_key', 'ctrlKey'); // Multi select key
        config()->set('curator.max_size', config('shazzoo_media.max_size')); // Max file size in KB
        config()->set('curator.accepted_file_types', [
            // Images
            'image/jpeg',  // .jpg, .jpeg
            'image/png',   // .png
            'image/webp',  // .webp
            'image/gif',   // .gif
            'image/svg+xml', // .svg

            // Documents
            'application/pdf',  // .pdf
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
            'application/vnd.ms-powerpoint', // .ppt
            'text/csv',  // .csv
            'application/json', // .json

            // Video
            'video/mp4',  // .mp4
            'video/quicktime',  // .mov

            // Audio
            'audio/mpeg',  // .mp3
            'audio/wav',   // .wav

            // Flash
            'application/x-shockwave-flash',  // .swf
        ]);

        ///test

        // Register the MediaPolicy if it exists in the config
        if (config('shazzoo_media.media_policies')) {
            $customPolicy = app_path('Policies/MediaPolicy.php');

            if (File::exists($customPolicy)) {
                Gate::policy(
                    $modelClass,
                    \App\Policies\MediaPolicy::class
                );
            } else {
                if (app()->environment('local')) {
                    Log::warning('ShazzooMedia: No MediaPolicy found — publish it using php artisan vendor:publish --tag=shazzoo-media-policy');
                }
            }
        }

        $this->app->bind(Media::class, $modelClass);

        if (app()->bound('livewire')) {
            Livewire::component('curator-panel', ShazzooMediaPanel::class);
        }

        // Register the Models observer
        $modelClass::flushEventListeners();
        $modelClass::observe(ShazzooMediaObserver::class);

        // Load the views from the package instead of from curator
        View::prependNamespace('curator', __DIR__ . '/../resources/views');
        View::prependNamespace('shazzoo_media', __DIR__ . '/../resources/views');
        View::addNamespace('livewire', __DIR__ . '/../resources/views');

        FilamentAsset::register([
            Css::make('curator', base_path('vendor/awcodes/filament-curator/resources/dist/curator.css'))->loadedOnRequest(false),
        ], 'finnwiel/shazzoo-media');
    }
}
