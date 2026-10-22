<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Glide\ShazzooMediaServerFactory;
use FinnWiel\ShazzooMedia\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ShazzooMediaServerFactoryTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/shazzoo-media-'.uniqid();
        $this->app->useStoragePath($this->storagePath);

        File::ensureDirectoryExists($this->storagePath.'/app/public/media/5');
        $image = imagecreatetruecolor(20, 20);
        imagepng($image, $this->storagePath.'/app/public/media/5/photo.png');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    public function test_conversions_land_in_a_directory_the_web_server_can_read()
    {
        (new ShazzooMediaServerFactory)->getFactory()->makeImage('media/5/photo.png', [
            'conversion' => 'medium',
            'w' => 10,
            'fm' => 'webp',
        ]);

        $directory = $this->storagePath.'/app/public/media/5/conversions';

        $this->assertFileExists($directory.'/photo-medium.webp');
        // Others need read and execute on the directory to serve the file.
        $this->assertSame(0005, fileperms($directory) & 0005);
    }
}
