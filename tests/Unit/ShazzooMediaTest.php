<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery;

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
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
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
            'name' => 'test.jpg',
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        // Create a fake conversion file
        Storage::disk('public')->put(
            'conversions/test/test-thumbnail.webp',
            'fake conversion content'
        );

        // Test that the conversion URL is properly formatted
        $this->assertStringContainsString('conversions/test/test-thumbnail.webp', $media->thumbnail_url);

        // Test that it falls back to original URL when conversion doesn't exist
        Storage::disk('public')->delete('conversions/test/test-thumbnail.webp');
        $this->assertEquals($media->url, $media->thumbnail_url);
    }

    public function test_it_casts_attributes_correctly()
    {
        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
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
            'name' => 'test.jpg',
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        // Create a fake conversion file
        Storage::disk('public')->put(
            'conversions/test/test-custom.webp',
            'fake conversion content'
        );

        // Test dynamic conversion URL access
        $this->assertStringContainsString('conversions/test/test-custom.webp', $media->custom_url);
    }

    public function test_it_handles_missing_conversion_urls()
    {
        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        // Test that non-existent conversion returns original URL
        $this->assertEquals($media->url, $media->nonexistent_conversion_url);
    }

    public function test_it_handles_file_deletion()
    {
        $media = ShazzooMedia::create([
            'name' => 'test.jpg',
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
            'size' => 1000,
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 100,
            'height' => 100,
        ]);

        // Create a fake conversion file
        Storage::disk('public')->put(
            'conversions/test/test-thumbnail.webp',
            'fake conversion content'
        );

        // Delete the media
        $media->delete();

        // Verify the file and conversions are deleted
        $this->assertFalse(Storage::disk('public')->exists('test.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('conversions/test/test-thumbnail.webp'));
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
            'path' => 'test.jpg',
            'disk' => 'public',
            'directory' => 'media',
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