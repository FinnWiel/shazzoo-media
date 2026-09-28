<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaUploader;
use FinnWiel\ShazzooMedia\Tests\TestCase;

class ShazzooMediaUploaderTest extends TestCase
{
    /**
     * Call the protected resize step directly, the way afterStateUpdated does
     * for every uploaded file. Warnings become exceptions, as Laravel's error
     * handler does in a running app.
     */
    private function resize(string $path): void
    {
        $uploader = ShazzooMediaUploader::make('file');

        set_error_handler(function (int $level, string $message): never {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            (fn (string $path) => $this->resizeImage($path))->call($uploader, $path);
        } finally {
            restore_error_handler();
        }
    }

    public function test_it_leaves_a_file_that_is_not_an_image_alone(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");

        $this->resize($path);

        $this->assertStringStartsWith('%PDF-1.4', file_get_contents($path));

        unlink($path);
    }

    public function test_it_scales_an_image_down_to_the_maximum_size(): void
    {
        config(['shazzoo_media.max_image_width' => 100, 'shazzoo_media.max_image_height' => 100]);

        $path = tempnam(sys_get_temp_dir(), 'img');
        imagepng(imagecreatetruecolor(400, 200), $path);

        $this->resize($path);

        [$width, $height] = getimagesize($path);
        $this->assertSame([100, 50], [$width, $height]);

        unlink($path);
    }
}
