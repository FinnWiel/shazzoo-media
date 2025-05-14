<?php

namespace FinnWiel\ShazzooMedia\Resources\MediaResource;

use Awcodes\Curator\CuratorPlugin;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use FinnWiel\ShazzooMedia\Models\MediaExtended;

class CreateMedia extends CreateRecord
{
    public static function getResource(): string
    {
        return CuratorPlugin::get()->getResource();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = auth()->guard()->user()->tenant_id ?? null;

        if (config('shazzoo_media.check_duplicates')) {
            if (isset($data['file']['path'])) {
                $disk = $data['file']['disk'] ?? 'public';
                $fullPath = Storage::disk($disk)->path($data['file']['path']);

                if (file_exists($fullPath)) {
                    $hash = md5_file($fullPath);

                    if (MediaExtended::where('file_hash', $hash)->where('tenant_id', $tenantId)->exists()) {
                        $this->form->fill([
                            'file' => null,
                        ]);

                        Storage::disk($disk)->delete($data['file']['path']);

                        Notification::make()
                            ->title('Duplicate File')
                            ->body('This file has already been uploaded.')
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
