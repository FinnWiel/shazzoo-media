<?php

namespace FinnWiel\ShazzooMedia\Observers;

use FinnWiel\ShazzooMedia\Models\MediaExtended as Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class CustomMediaObserver
{
    /**
     * Handle the Media "creating" event.
     */
    public function creating(Media $media): void
    {
        if ($this->hasMediaUpload($media)) {
            foreach ($media->file as $k => $v) {
                if ($k === 'name') {
                    if (is_string($v)) {
                        $media->{$k} = $v;
                    } else {
                        $media->{$k} = $v->toString();
                    }
                } elseif ($k === 'exif' && is_array($v)) {
                    // Fix malformed utf-8 characters
                    array_walk_recursive($v, function (&$entry) {
                        if (! mb_detect_encoding($entry, 'utf-8', true)) {
                            $entry = mb_convert_encoding($entry, 'utf-8');
                        }
                    });

                    $media->{$k} = $v;
                } else {
                    $media->{$k} = $v;
                }
            }
        }

        $media->__unset('file');
    }

    /**
     * Handle the Media "updating" event.
     */
    public function updating(Media $media): void
    {
        // Replace image
        if ($this->hasMediaUpload($media)) {
            if (Storage::disk($media->disk)->exists($media->directory . '/' . $media->getOriginal()['name'] . '.' . $media->getOriginal()['ext'])) {
                Storage::disk($media->disk)->delete($media->directory . '/' . $media->getOriginal()['name'] . '.' . $media->getOriginal()['ext']);
            }

            foreach ($media->file as $k => $v) {
                $media->{$k} = $v;
            }

            Storage::disk($media->disk)->move($media->path, $media->directory . '/' . $media->getOriginal()['name'] . '.' . $media->ext);

            $media->name = $media->getOriginal()['name'];
            $media->path = $media->directory . '/' . $media->getOriginal()['name'] . '.' . $media->ext;

            // Delete glide-cache for replaced image
            $server = app(config('curator.glide.server'))->getFactory();
            $server->deleteCache($media->path);
        }

        // Rename file name
        if ($media->isDirty(['name']) && ! blank($media->name)) {
            if (Storage::disk($media->disk)->exists($media->directory . '/' . $media->name . '.' . $media->ext)) {
                $media->name = $media->name . '-' . time();
            }
            Storage::disk($media->disk)->move($media->path, $media->directory . '/' . $media->name . '.' . $media->ext);
            $media->path = $media->directory . '/' . $media->name . '.' . $media->ext;
        }

        $media->__unset('file');
        $media->__unset('originalFilename');
    }

    public function deleted(Media $media): void
    {
        $path = $media->path;
        $directory = trim($media->directory, '/');

        // Delete the main media file
        Storage::disk($media->disk)->delete($path);
        Log::info("✅ Deleted file: {$path} from {$media->disk}");

        // Safeguard directory cleanup
        $protectedDirs = ['public', '', '.', '/', 'media', 'storage'];
        Log::info("Normalized directory: {$directory}");
        Log::info("Protected directories: " . implode(', ', $protectedDirs));

        if (!in_array($directory, $protectedDirs, true)) {
            $fileCount = count(Storage::disk($media->disk)->allFiles($directory));
            Log::info("File count in {$directory}: {$fileCount}");

            if ($fileCount === 0) {
                Storage::disk($media->disk)->deleteDirectory($directory);
                Log::info("✅ Deleted directory: {$directory} from {$media->disk}");
            } else {
                Log::info("⛔ Directory {$directory} not empty, skipping delete.");
            }
        } else {
            Log::warning("⛔ Attempted to delete protected directory: {$directory}, skipped.");
        }

        // ✅ Delete related Glide conversions folder by UUID
        $uuid = pathinfo($media->path, PATHINFO_FILENAME);

        if ($uuid && !Str::contains($uuid, ['/', '..'])) {
            $conversionPath = "conversions/{$uuid}";
            if (Storage::disk('public')->exists($conversionPath)) {
                Storage::disk('public')->deleteDirectory($conversionPath);
                Log::info("✅ Deleted conversions directory: {$conversionPath}");
            } else {
                Log::info("ℹ️ No conversions found for: {$conversionPath}");
            }
        } else {
            Log::warning("⛔ Unsafe or missing UUID for conversions: {$uuid}");
        }
    }

    private function hasMediaUpload($media): bool
    {
        return is_array($media->file) || $media->file instanceof stdClass;
    }
}
