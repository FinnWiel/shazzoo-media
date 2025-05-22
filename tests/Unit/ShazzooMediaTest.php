<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ShazzooMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_can_create_a_media_record()
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => $file->getSize(),
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        $this->assertInstanceOf(ShazzooMedia::class, $media);
        $this->assertEquals('test.jpg', $media->name);
        $this->assertEquals('public', $media->disk);
    }

    public function test_it_handles_conversion_urls()
    {
        $media = ShazzooMedia::create([
            'id' => 1,
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        $conversionPath = 'media/1/conversions/test-thumbnail.webp';

        Storage::disk('public')->put($conversionPath, 'fake conversion content');

        $this->assertStringContainsString($conversionPath, $media->thumbnail_url);

        Storage::disk('public')->delete($conversionPath);

        $this->assertEquals($media->url, $media->thumbnail_url);
    }

    public function test_it_casts_attributes_correctly()
    {
        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => '1000',
            'type' => 'image',
            'ext' => 'jpg',
            'width' => '100',
            'height' => '100',
            'curations' => ['test' => 'value'],
            'conversions' => ['test' => 'value'],
            'exif' => ['test' => 'value'],
        ]);

        $this->assertIsInt($media->width);
        $this->assertIsInt($media->height);
        $this->assertIsInt($media->size);
        $this->assertIsArray($media->curations);
        $this->assertIsArray($media->conversions);
        $this->assertIsArray($media->exif);
    }

    public function test_it_handles_dynamic_conversion_urls()
    {
        $media = ShazzooMedia::create([
            'id' => 1,
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        $conversionPath = 'media/1/conversions/test-custom.webp';

        Storage::disk('public')->put($conversionPath, 'fake conversion content');

        $this->assertStringContainsString($conversionPath, $media->custom_url);
    }

    public function test_it_handles_missing_conversion_urls()
    {
        $media = ShazzooMedia::create([
            'id' => 1,
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        $this->assertEquals($media->url, $media->nonexistent_conversion_url);
    }

    public function test_it_handles_file_deletion()
    {
        $media = ShazzooMedia::create([
            'id' => 1,
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        Storage::disk('public')->put('media/1/test.jpg', 'fake file');
        Storage::disk('public')->put('media/1/conversions/test-thumbnail.webp', 'fake conversion');

        $media->delete();

        $this->assertFalse(Storage::disk('public')->exists('media/1/test.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('media/1/conversions/test-thumbnail.webp'));
    }

    public function test_it_handles_exif_data()
    {
        $exif = [
            'Make' => 'Canon',
            'Model' => 'EOS 5D',
            'DateTime' => '2024:01:01 12:00:00'
        ];

        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'media/1/test.jpg',
            'disk' => 'public',
            'directory' => 'media/1',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
            'exif' => $exif
        ]);

        $this->assertEquals($exif, $media->exif);
        $this->assertEquals('Canon', $media->exif['Make']);
    }
}
