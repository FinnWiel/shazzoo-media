<?php

namespace FinnWiel\ShazzooMedia\Resources\MediaResource;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use FinnWiel\ShazzooMedia\Resources\MediaResource;
use FinnWiel\ShazzooMedia\Services\DuplicateChecker;


class CreateMedia extends CreateRecord
{
    public static function getResource(): string
    {
        return MediaResource::class;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (config('shazzoo_media.check_duplicates')) {
            if (isset($data['file']['path'])) {
                $disk = $data['file']['disk'] ?? 'public';
                $fullPath = Storage::disk($disk)->path($data['file']['path']);

                if (file_exists($fullPath)) {
                    $hash = md5_file($fullPath);

                    if (ShazzooMedia::where('file_hash', $hash)->exists()) {
                        $this->form->fill([
                            'file' => null,
                        ]);

                        Storage::disk($disk)->delete($data['file']['path']);

                        Notification::make()
                            ->title(trans('shazzoo_media::notifications.exeptions.duplicate.title'))
                            ->body(trans('shazzoo_media::notifications.exeptions.duplicate.resource'))
                            ->danger()
                            ->send();

                        throw ValidationException::withMessages([
                            'file' => 'This file has already been uploaded.',
                        ]);
                    }

                    $data['file_hash'] = $hash;
                }
            }
        }

        if (blank($data['title'])) {
            $data['title'] = pathinfo($data['originalFilename'], PATHINFO_FILENAME);
        }

        unset($data['originalFilename']);

        return $data;
    }
}
