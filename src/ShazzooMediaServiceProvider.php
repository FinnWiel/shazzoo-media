<?php

namespace FinnWiel\ShazzooMedia;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use FinnWiel\ShazzooMedia\Commands\ClearConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Commands\ClearMedia;
use FinnWiel\ShazzooMedia\Commands\GenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\ListConversionDefinitions;
use FinnWiel\ShazzooMedia\Commands\RegenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\SetConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Models\MediaExtended;
use FinnWiel\ShazzooMedia\Observers\CustomMediaObserver;
use FinnWiel\ShazzooMedia\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

class ShazzooMediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('shazzoo_media')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_media_table',
                'add_role_and_tenant_to_user_table',
            ])
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
        $this->loadViewsFrom(__DIR__ . '/../resources/views/vendor/curator', 'curator');

        // Set all changes for curator conifig to work with shazzoo media
        config()->set('curator.resources.resource', \FinnWiel\ShazzooMedia\Resources\MediaResource::class); // Resource
        config()->set('curator.model', \FinnWiel\ShazzooMedia\Models\MediaExtended::class); // Model
        config()->set('curator.glide.server', \FinnWiel\ShazzooMedia\Glide\CustomServerFactory::class); // Glide server
        config()->set('curator.tabs.display_curation', false); // Display curation tab
        config()->set('curator.tabs.display_upload_new', false); // Display upload new tab
        config()->set('curator.multi_select_key', 'ctrlKey'); // Multi select key

        // Register the MediaPolicy if it exists in the config
        if (config('shazzoo_media.media_policies')) {
            Gate::policy(\FinnWiel\ShazzooMedia\Models\MediaExtended::class, MediaPolicy::class);
        }

        // Register the Models observer
        MediaExtended::flushEventListeners();
        MediaExtended::observe(CustomMediaObserver::class);

        // Load the views from the package instead of from curator
        View::prependNamespace('curator',__DIR__ . '/../resources/views/vendor/curator');

        FilamentAsset::register([
            Css::make('curator', base_path('vendor/awcodes/filament-curator/resources/dist/curator.css'))->loadedOnRequest(false),
        ], 'finnwiel/shazzoo-media');
    }
}
