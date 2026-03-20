<?php

namespace FinnWiel\ShazzooMedia;

use App\Policies\MediaPolicy;
use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Glide\SymfonyResponseFactory;
use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\MediaResource as CuratorMediaResource;
use Awcodes\Curator\Resources\Media\Pages\CreateMedia as CuratorCreateMedia;
use Awcodes\Curator\Resources\Media\Pages\EditMedia as CuratorEditMedia;
use Awcodes\Curator\Resources\Media\Pages\ListMedia as CuratorListMedia;
use FinnWiel\ShazzooMedia\Commands\ClearConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Commands\ClearMedia;
use FinnWiel\ShazzooMedia\Commands\ClearMediaConversions;
use FinnWiel\ShazzooMedia\Commands\GenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\ListConversionDefinitions;
use FinnWiel\ShazzooMedia\Commands\RegenerateConversionImages;
use FinnWiel\ShazzooMedia\Commands\SetConversionDatabaseRecords;
use FinnWiel\ShazzooMedia\Components\Modals\ShazzooMediaPanel;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Observers\ShazzooMediaObserver;
use FinnWiel\ShazzooMedia\Resources\MediaResource;
use FinnWiel\ShazzooMedia\Resources\MediaResource\CreateMedia as ShazzooCreateMedia;
use FinnWiel\ShazzooMedia\Resources\MediaResource\EditMedia as ShazzooEditMedia;
use FinnWiel\ShazzooMedia\Resources\MediaResource\ListMedia as ShazzooListMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
                ClearMediaConversions::class,
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

    public function packageRegistered(): void
    {
        $this->overrideCuratorConfig();
        $this->bindCuratorClasses();
    }

    public function packageBooted(): void
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);

        $this->overrideCuratorConfig();
        $this->bindCuratorClasses();
        $this->configureGlideServer();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'curator');

        $this->publishes([
            __DIR__.'/Policies/MediaPolicy.php.stub' => app_path('Policies/MediaPolicy.php'),
        ], 'shazzoo-media-policy');

        $this->publishes([
            __DIR__.'/Models/ShazzooMedia.php.stub' => app_path('Models/ShazzooMedia.php'),
        ], 'shazzoo-media-model');

        // Register the MediaPolicy if it exists in the config
        if (config('shazzoo_media.media_policies')) {
            $customPolicy = app_path('Policies/MediaPolicy.php');

            if (File::exists($customPolicy)) {
                Gate::policy(
                    $modelClass,
                    MediaPolicy::class
                );
            } else {
                if (app()->environment('local')) {
                    Log::warning('ShazzooMedia: No MediaPolicy found — publish it using php artisan vendor:publish --tag=shazzoo-media-policy');
                }
            }
        }

        if (app()->bound('livewire')) {
            Livewire::component('curator-panel', ShazzooMediaPanel::class);
        }

        // Register the Models observer
        $modelClass::flushEventListeners();
        $modelClass::observe(ShazzooMediaObserver::class);

        // Load the views from the package instead of from curator
        View::prependNamespace('curator', __DIR__.'/../resources/views');
        View::prependNamespace('shazzoo_media', __DIR__.'/../resources/views');
        View::addNamespace('livewire', __DIR__.'/../resources/views');
    }

    protected function overrideCuratorConfig(): void
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);
        $defaultDirectory = config('shazzoo_media.directory', 'media');
        $glideToken = config('curator.glide_token') ?: config('app.key');

        if (blank($glideToken)) {
            $glideToken = sha1((string) base_path());
        }

        config()->set('curator.resource.resource', MediaResource::class);
        config()->set('curator.resource.pages.create', ShazzooCreateMedia::class);
        config()->set('curator.resource.pages.edit', ShazzooEditMedia::class);
        config()->set('curator.resource.pages.index', ShazzooListMedia::class);
        config()->set('curator.model', $modelClass);
        config()->set('curator.default_directory', $defaultDirectory);
        config()->set('curator.glide_token', $glideToken);
    }

    protected function bindCuratorClasses(): void
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);

        $this->app->bind(Media::class, $modelClass);
        $this->app->bind(CuratorMediaResource::class, MediaResource::class);
        $this->app->bind(CuratorCreateMedia::class, ShazzooCreateMedia::class);
        $this->app->bind(CuratorEditMedia::class, ShazzooEditMedia::class);
        $this->app->bind(CuratorListMedia::class, ShazzooListMedia::class);
    }

    protected function configureGlideServer(): void
    {
        Glide::serverConfig([
            'driver' => 'gd',
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => storage_path('app'),
            'source_path_prefix' => 'public',
            'cache' => storage_path('app'),
            'cache_path_prefix' => '.cache',
            'max_image_size' => 2000 * 2000,
        ]);
    }
}
