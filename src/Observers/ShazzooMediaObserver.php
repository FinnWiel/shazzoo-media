<?php

namespace FinnWiel\ShazzooMedia\Observers;

use FinnWiel\ShazzooMedia\Exceptions\DuplicateMediaException;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class ShazzooMediaObserver
{
    /**
     * Handle the Media "creating" event.
     */
    public function creating(ShazzooMedia $media): void
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


                $fullPath = Storage::disk($media->file['disk'])->path($media->file['path']);
                if (file_exists($fullPath)) {
                    $hash = md5_file($fullPath);
                    $media->file_hash = $hash;
                }

                if (config('shazzoo_media.check_duplicates') && $hash) {
                    // Check for existing file with the same hash
                    $duplicate = ShazzooMedia::query()
                        ->where('file_hash', $hash)
                        ->first();

                    if ($duplicate) {
                        // Delete the uploaded file to avoid orphaned media
                        if (Storage::disk($media->file['disk'])->exists($media->file['path'])) {
                            Storage::disk($media->file['disk'])->delete($media->file['path']);
                        }

                        throw new DuplicateMediaException($duplicate);
                        // throw new \Exception('Duplicate media detected.');
                    }
                }
            }
        }

        $media->__unset('file');
    }

    /**
     * Handle the Media "created" event.
     */
    public function created(ShazzooMedia $media): void
    {
        // Build new path: media_id/filename.ext (Spatie-style)
        $newPath = "media/{$media->id}/{$media->name}.{$media->ext}";
        $disk = Storage::disk($media->disk);

        // Move the file if it exists at the original path
        if ($disk->exists($media->path)) {
            $disk->makeDirectory(dirname($newPath));
            $disk->move($media->path, $newPath);

            $media->path = $newPath;
            $media->directory = "media/{$media->id}";
            $media->save();
        }
    }

    /**
     * Handle the Media "updating" event.
     */
    public function updating(ShazzooMedia $media): void
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

            $oldName = $media->getOriginal('name');
            $newName = $media->name;

            $conversionBaseDir = 'conversions/' . $oldName;
            $newConversionBaseDir = 'conversions/' . $newName;

            $disk = Storage::disk($media->disk);

            if ($disk->exists($conversionBaseDir)) {
                // Make new directory if needed
                $disk->makeDirectory($newConversionBaseDir);

                $conversionFiles = $disk->files($conversionBaseDir);

                foreach ($conversionFiles as $filePath) {
                    $filename = basename($filePath);
                    $newFilename = str_replace($oldName, $newName, $filename);

                    $disk->move(
                        $filePath,
                        $newConversionBaseDir . '/' . $newFilename
                    );
                }

                // Optionally delete old conversion directory
                $disk->deleteDirectory($conversionBaseDir);
            }
        }

        $media->__unset('file');
        $media->__unset('originalFilename');
    }

    /**
     * Handle the Media "deleted" event.
     */
    public function deleted(ShazzooMedia $media): void
    {
        $disk = Storage::disk($media->disk);
        $path = $media->path;
        $directory = trim($media->directory, '/');

        // Delete the main media file
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        // Delete the conversions directory (Spatie-style: media/{id}/conversions/)
        $conversionPath = "media/{$media->id}/conversions";
        if ($disk->exists($conversionPath)) {
            $disk->deleteDirectory($conversionPath);
        }

        // Clean up directory if empty, avoid deleting top-level dirs
        $protectedDirs = ['public', '', '.', '/', 'media', 'storage'];

        if (!in_array($directory, $protectedDirs, true)) {
            $fileCount = count($disk->allFiles($directory));
            if ($fileCount === 0) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    private function hasMediaUpload($media): bool
    {
        return is_array($media->file) || $media->file instanceof stdClass;
    }
}
