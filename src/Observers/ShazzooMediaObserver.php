<?php

namespace FinnWiel\ShazzooMedia\Observers;

use Awcodes\Curator\Facades\Glide;
use FinnWiel\ShazzooMedia\Exceptions\DuplicateMediaException;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Throwable;

class ShazzooMediaObserver
{
    /**
     * Get the model class from config.
     */
    protected function getModelClass(): string
    {
        return config('shazzoo_media.model', ShazzooMedia::class);
    }

    /**
     * Handle the Media "creating" event.
     */
    public function creating($media): void
    {
        if ($this->hasMediaUpload($media)) {
            foreach ($media->file as $k => $v) {
                if ($k === 'name') {
                    $media->{$k} = is_string($v) ? $v : $v->toString();
                } elseif ($k === 'exif' && is_array($v)) {
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

            $fullPath = Storage::disk($media->file['disk'])->path($media->file['path']);
            if (file_exists($fullPath)) {
                $hash = md5_file($fullPath);
                $media->file_hash = $hash;

                if (config('shazzoo_media.check_duplicates')) {
                    $modelClass = $this->getModelClass();

                    $duplicate = $modelClass::query()
                        ->where('file_hash', $hash)
                        ->first();

                    if ($duplicate) {
                        if (Storage::disk($media->file['disk'])->exists($media->file['path'])) {
                            Storage::disk($media->file['disk'])->delete($media->file['path']);
                        }

                        throw new DuplicateMediaException($duplicate);
                    }
                }
            }
        }

        $media->__unset('file');
    }

    /**
     * Handle the Media "created" event.
     */
    public function created($media): void
    {
        $newPath = "media/{$media->id}/{$media->name}.{$media->ext}";
        $disk = Storage::disk($media->disk);

        if ($disk->exists($media->path)) {
            $disk->makeDirectory(dirname($newPath));
            $disk->move($media->path, $newPath);

            $media->path = $newPath;
            $media->directory = "media/{$media->id}";

            // Quietly: the "created" event fires before Eloquent syncs the original
            // attributes, so every attribute still reads as dirty here. A normal
            // save() would re-enter updating() and trip the rename branch below.
            $media->saveQuietly();
        }
    }

    /**
     * Handle the Media "updating" event.
     */
    public function updating($media): void
    {
        if ($this->hasMediaUpload($media)) {
            $original = $media->getOriginal();

            if (Storage::disk($media->disk)->exists($media->directory.'/'.$original['name'].'.'.$original['ext'])) {
                Storage::disk($media->disk)->delete($media->directory.'/'.$original['name'].'.'.$original['ext']);
            }

            foreach ($media->file as $k => $v) {
                $media->{$k} = $v;
            }

            Storage::disk($media->disk)->move(
                $media->path,
                $media->directory.'/'.$original['name'].'.'.$media->ext
            );

            $media->name = $original['name'];
            $media->path = $media->directory.'/'.$original['name'].'.'.$media->ext;

            $server = Glide::getServer();
            $server->deleteCache($media->path);
        }

        $oldName = $media->getOriginal('name');

        // $oldName is null while the record is being created: the "created" event
        // fires before syncOriginal(), so name reads as dirty for a brand new row.
        if ($media->isDirty(['name']) && ! blank($media->name) && ! is_null($oldName)) {
            $disk = Storage::disk($media->disk);
            $newFilePath = $media->directory.'/'.$media->name.'.'.$media->ext;

            if ($newFilePath !== $media->path) {
                if ($disk->exists($newFilePath)) {
                    $media->name .= '-'.time();
                    $newFilePath = $media->directory.'/'.$media->name.'.'.$media->ext;
                }

                if ($this->moveFile($media->disk, $media->path, $newFilePath)) {
                    $media->path = $newFilePath;

                    $this->moveConversions($disk, $oldName, $media->name);
                } else {
                    // Leave path pointing at the file that is actually on disk,
                    // otherwise the record renders a URL that 404s.
                    Log::warning('ShazzooMedia: failed to rename media file, keeping the existing path.', [
                        'media_id' => $media->id,
                        'disk' => $media->disk,
                        'from' => $media->path,
                        'to' => $newFilePath,
                    ]);
                }
            }
        }

        $media->__unset('file');
        $media->__unset('originalFilename');
    }

    /**
     * Handle the Media "deleted" event.
     */
    public function deleted($media): void
    {
        $disk = Storage::disk($media->disk);
        $path = $media->path;
        $directory = trim($media->directory, '/');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        $conversionPath = "media/{$media->id}/conversions";
        if ($disk->exists($conversionPath)) {
            $disk->deleteDirectory($conversionPath);
        }

        $protectedDirs = ['public', '', '.', '/', 'media', 'storage'];
        if (! in_array($directory, $protectedDirs, true)) {
            if (count($disk->allFiles($directory)) === 0) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    /**
     * Move a file on the given disk, reporting whether it actually moved.
     *
     * Storage::move() returns false rather than throwing on disks configured with
     * throw => false (the default for the "public" disk), so the result has to be
     * checked before the new path is written back to the record.
     */
    private function moveFile(string $disk, string $from, string $to): bool
    {
        try {
            return Storage::disk($disk)->move($from, $to);
        } catch (Throwable $e) {
            Log::warning('ShazzooMedia: exception while moving media file.', [
                'disk' => $disk,
                'from' => $from,
                'to' => $to,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Move the conversions that belong to a renamed media item.
     */
    private function moveConversions($disk, string $oldName, string $newName): void
    {
        $conversionBaseDir = 'conversions/'.$oldName;
        $newConversionBaseDir = 'conversions/'.$newName;

        if (! $disk->exists($conversionBaseDir)) {
            return;
        }

        $disk->makeDirectory($newConversionBaseDir);

        foreach ($disk->files($conversionBaseDir) as $filePath) {
            $filename = basename($filePath);
            $newFilename = str_replace($oldName, $newName, $filename);

            $disk->move(
                $filePath,
                $newConversionBaseDir.'/'.$newFilename
            );
        }

        $disk->deleteDirectory($conversionBaseDir);
    }

    /**
     * Check if the media object has a file upload.
     */
    private function hasMediaUpload($media): bool
    {
        return is_array($media->file) || $media->file instanceof stdClass;
    }
}
