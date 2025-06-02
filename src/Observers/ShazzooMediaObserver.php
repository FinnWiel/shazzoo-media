<?php

namespace FinnWiel\ShazzooMedia\Observers;

use FinnWiel\ShazzooMedia\Exceptions\DuplicateMediaException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class ShazzooMediaObserver
{
    /**
     * Get the model class from config.
     */
    protected function getModelClass(): string
    {
        return config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);
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
                        if (!mb_detect_encoding($entry, 'utf-8', true)) {
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
            $media->save();
        }
    }

    /**
     * Handle the Media "updating" event.
     */
    public function updating($media): void
    {
        if ($this->hasMediaUpload($media)) {
            $original = $media->getOriginal();

            if (Storage::disk($media->disk)->exists($media->directory . '/' . $original['name'] . '.' . $original['ext'])) {
                Storage::disk($media->disk)->delete($media->directory . '/' . $original['name'] . '.' . $original['ext']);
            }

            foreach ($media->file as $k => $v) {
                $media->{$k} = $v;
            }

            Storage::disk($media->disk)->move(
                $media->path,
                $media->directory . '/' . $original['name'] . '.' . $media->ext
            );

            $media->name = $original['name'];
            $media->path = $media->directory . '/' . $original['name'] . '.' . $media->ext;

            $server = app(config('curator.glide.server'))->getFactory();
            $server->deleteCache($media->path);
        }

        if ($media->isDirty(['name']) && !blank($media->name)) {
            $newFilePath = $media->directory . '/' . $media->name . '.' . $media->ext;

            if (Storage::disk($media->disk)->exists($newFilePath)) {
                $media->name .= '-' . time();
            }

            Storage::disk($media->disk)->move($media->path, $newFilePath);
            $media->path = $newFilePath;

            $oldName = $media->getOriginal('name');
            $newName = $media->name;

            $conversionBaseDir = 'conversions/' . $oldName;
            $newConversionBaseDir = 'conversions/' . $newName;

            $disk = Storage::disk($media->disk);

            if ($disk->exists($conversionBaseDir)) {
                $disk->makeDirectory($newConversionBaseDir);
                foreach ($disk->files($conversionBaseDir) as $filePath) {
                    $filename = basename($filePath);
                    $newFilename = str_replace($oldName, $newName, $filename);

                    $disk->move(
                        $filePath,
                        $newConversionBaseDir . '/' . $newFilename
                    );
                }
                $disk->deleteDirectory($conversionBaseDir);
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
        if (!in_array($directory, $protectedDirs, true)) {
            if (count($disk->allFiles($directory)) === 0) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    /**
     * Check if the media object has a file upload.
     */
    private function hasMediaUpload($media): bool
    {
        return is_array($media->file) || $media->file instanceof stdClass;
    }
}
