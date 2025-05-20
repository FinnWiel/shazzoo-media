<?php

namespace FinnWiel\ShazzooMedia\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use FinnWiel\ShazzooMedia\ShazzooMediaServiceProvider;
use Illuminate\Support\Facades\Artisan;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ShazzooMediaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure all service providers have been booted
        $this->app->boot();

        // Run migrations from test migrations folder
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }
} 