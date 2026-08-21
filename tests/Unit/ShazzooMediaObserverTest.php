<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ShazzooMediaObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Create a media record the way an upload does: the file lands in the
     * staging directory and created() moves it into media/{id}/.
     */
    private function createMedia(string $name = 'test', string $contents = 'original'): ShazzooMedia
    {
        Storage::disk('public')->put("media/{$name}.jpg", $contents);

        return ShazzooMedia::create([
            'name' => $name,
            'path' => "media/{$name}.jpg",
            'disk' => 'public',
            'directory' => 'media',
            'visibility' => 'public',
            'size' => strlen($contents),
            'type' => 'image',
            'ext' => 'jpg',
            'width' => 10,
            'height' => 10,
        ]);
    }

    public function test_creating_a_media_record_does_not_rename_it(): void
    {
        $media = $this->createMedia('test');

        $media->refresh();

        $this->assertSame('test', $media->name);
        $this->assertSame("media/{$media->id}/test.jpg", $media->path);
        $this->assertSame("media/{$media->id}", $media->directory);
        $this->assertTrue(Storage::disk('public')->exists($media->path));
    }

    public function test_renaming_to_a_free_name_moves_the_file(): void
    {
        $media = $this->createMedia('test');
        $oldPath = $media->path;

        $media->name = 'renamed';
        $media->save();

        $media->refresh();

        $this->assertSame('renamed', $media->name);
        $this->assertSame("media/{$media->id}/renamed.jpg", $media->path);
        $this->assertTrue(Storage::disk('public')->exists($media->path));
        $this->assertFalse(Storage::disk('public')->exists($oldPath));
        $this->assertSame('original', Storage::disk('public')->get($media->path));
    }

    public function test_renaming_to_a_colliding_name_keeps_name_and_path_in_sync(): void
    {
        $media = $this->createMedia('test');
        $collidingPath = "media/{$media->id}/taken.jpg";

        Storage::disk('public')->put($collidingPath, 'someone else');

        $media->name = 'taken';
        $media->save();

        $media->refresh();

        $this->assertStringStartsWith('taken-', $media->name);
        $this->assertSame("media/{$media->id}/{$media->name}.jpg", $media->path);
        $this->assertTrue(Storage::disk('public')->exists($media->path));
        $this->assertSame('original', Storage::disk('public')->get($media->path));

        // The file that was already there must not be clobbered.
        $this->assertSame('someone else', Storage::disk('public')->get($collidingPath));
    }

    public function test_a_failed_move_leaves_the_stored_path_alone(): void
    {
        $media = $this->createMedia('test');
        $originalPath = $media->path;

        // Make the move fail: there is nothing left at the source path.
        Storage::disk('public')->delete($originalPath);

        $media->name = 'renamed';
        $media->save();

        $media->refresh();

        $this->assertSame($originalPath, $media->path);
        $this->assertFalse(Storage::disk('public')->exists("media/{$media->id}/renamed.jpg"));
    }

    public function test_saving_without_touching_the_name_leaves_the_file_where_it_is(): void
    {
        $media = $this->createMedia('test');
        $path = $media->path;

        $media->alt = 'An alt text';
        $media->save();

        $media->refresh();

        $this->assertSame('test', $media->name);
        $this->assertSame($path, $media->path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_repair_command_repoints_a_record_at_the_only_file_in_its_directory(): void
    {
        $media = $this->createMedia('test');
        $realPath = $media->path;

        // Reproduce the corruption: the row points somewhere the file is not.
        $media->forceFill(['path' => "media/{$media->id}/gone.jpg", 'name' => 'gone'])->saveQuietly();

        $this->artisan('media:repair-paths')->assertSuccessful();

        $media->refresh();

        $this->assertSame($realPath, $media->path);
        $this->assertSame('test', $media->name);
    }

    public function test_repair_command_skips_directories_with_more_than_one_file(): void
    {
        $media = $this->createMedia('test');
        $brokenPath = "media/{$media->id}/gone.jpg";

        Storage::disk('public')->put("media/{$media->id}/extra.jpg", 'extra');
        $media->forceFill(['path' => $brokenPath])->saveQuietly();

        $this->artisan('media:repair-paths')->assertSuccessful();

        $media->refresh();

        $this->assertSame($brokenPath, $media->path);
    }

    public function test_repair_command_dry_run_changes_nothing(): void
    {
        $media = $this->createMedia('test');
        $brokenPath = "media/{$media->id}/gone.jpg";

        $media->forceFill(['path' => $brokenPath])->saveQuietly();

        $this->artisan('media:repair-paths', ['--dry-run' => true])->assertSuccessful();

        $media->refresh();

        $this->assertSame($brokenPath, $media->path);
    }
}
