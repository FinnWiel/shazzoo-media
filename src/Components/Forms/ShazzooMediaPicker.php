<?php

namespace FinnWiel\ShazzooMedia\Components\Forms;

use Awcodes\Curator\Components\Forms\CuratorPicker as CuratorPicker;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Artisan;

class ShazzooMediaPicker extends CuratorPicker
{
    protected static array $conversionRegistry = [];
    public bool $keepOriginalSize = false;
    protected bool $onlySvg = false;

    /**
     * Register conversions for the field.
     *
     * @param array $data The conversions to register.
     * @return static
     */
    public function conversions(array $data): static
    {
        if (isset(static::$conversionRegistry[$this->getName()])) {
            static::$conversionRegistry[$this->getName()] = array_merge(
                static::$conversionRegistry[$this->getName()],
                $data
            );
        } else {
            static::$conversionRegistry[$this->getName()] = $data;
        }

        return $this;
    }

    /**
     * Get the conversions for a specific field.
     *
     * @param string $field The field name to get conversions for.
     * @return array The conversions associated with the field.
     */
    public static function getConversionsFor(string $field): array
    {
        return static::$conversionRegistry[$field] ?? [];
    }

    /**
     * Save conversions to media based on the provided form data.
     *
     * @param array $formData The form data containing media IDs.
     * @return void
     */
    public static function saveConversionsToMedia(array $formData): void
    {
        $modelClass = config('shazzoo_media.model', \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class);

        foreach (static::$conversionRegistry as $field => $conversions) {
            $mediaIds = static::findValuesInNestedArray($formData, $field);

            if (empty($mediaIds)) {
                continue;
            }

            foreach ($mediaIds as $mediaId) {
                $media = $modelClass::find($mediaId);

                if (!$media) {
                    continue;
                }

                $existingConversions = json_decode($media->conversions, true) ?? [];
                $newConversions = array_diff($conversions, $existingConversions);

                if (empty($newConversions)) {
                    continue;
                }

                $mergedConversions = array_merge($existingConversions, $newConversions);

                $media->conversions = json_encode($mergedConversions);
                $media->save();

                Artisan::call('media:conversions:generate', ['--id' => $media->id]);
            }
        }
    }

    /**
     * Set the accepted file types for the media picker.
     *
     * @param string|array|null $types The file type group(s) to accept.
     * @return static
     */
    public function fileType(string|array|null $types = null): static
    {
        if (is_null($types)) {
            // Accept all file types (no restrictions)
            $this->acceptedFileTypes([]);
            return $this;
        }

        $types = (array) $types; // Ensure it's always an array

        $groupMap = [
            'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'icon' => ['image/svg+xml'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ];

        $accepted = collect($types)
            ->flatMap(fn($type) => $groupMap[$type] ?? [])
            ->unique()
            ->values()
            ->all();

        $this->acceptedFileTypes($accepted);

        return $this;
    }



    /**
     * Helper method to search for values in a nested array by key.
     *
     * @param array $data The array to search.
     * @param string $key The key to search for.
     * @return array The values associated with the key, or an empty array if not found.
     */
    protected static function findValuesInNestedArray(array $data, string $key): array
    {
        $results = [];

        foreach ($data as $k => $v) {
            if ($k === $key) {
                // Merge array if value is array, or wrap in array if scalar
                $results = array_merge($results, is_array($v) ? $v : [$v]);
            }

            if (is_array($v)) {
                $results = array_merge($results, static::findValuesInNestedArray($v, $key));
            }
        }

        return $results;
    }


    /**
     * Set the state path for the component.
     *
     * @param string $statePath The state path to set.
     * @return static
     */
    public function keepOriginalSize(bool $value = false): static
    {
        $this->keepOriginalSize = $value;

        return $this;
    }

    /**
     * Get the state path for the component.
     *
     * @return string
     */
    public function shouldKeepOriginalSize(): bool
    {
        return $this->keepOriginalSize;
    }

    /**
     * Get the action to open the Curator picker.
     *
     * @return Action
     */
    public function getPickerAction(): Action
    {
        return Action::make('open_curator_picker')
            ->label(trans('shazzoo_media::views.picker.select'))
            ->button()
            ->size('md')
            ->color('primary')
            ->icon('heroicon-s-photo')
            ->outlined(true)
            ->action(function (CuratorPicker $component, \Livewire\Component $livewire) {
                $livewire->dispatch('open-modal', id: 'curator-panel', settings: [
                    'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                    'defaultSort' => $component->getDefaultPanelSort(),
                    'directory' => $component->getDirectory(),
                    'diskName' => $component->getDiskName(),
                    'imageCropAspectRatio' => $component->getImageCropAspectRatio(),
                    'imageResizeMode' => $component->getImageResizeMode(),
                    'imageResizeTargetWidth' => $component->getImageResizeTargetWidth(),
                    'imageResizeTargetHeight' => $component->getImageResizeTargetHeight(),
                    'isLimitedToDirectory' => $component->isLimitedToDirectory(),
                    'isTenantAware' => $component->isTenantAware(),
                    'tenantOwnershipRelationshipName' => $component->tenantOwnershipRelationshipName(),
                    'isMultiple' => $component->isMultiple(),
                    'maxItems' => $component->getMaxItems(),
                    'maxSize' => $component->getMaxSize(),
                    'maxWidth' => $component->getMaxWidth(),
                    'minSize' => $component->getMinSize(),
                    'pathGenerator' => $component->getPathGenerator(),
                    'rules' => $component->getValidationRules(),
                    'selected' => collect($component->getState())->pluck('id')->filter()->values()->all(),
                    'shouldPreserveFilenames' => $component->shouldPreserveFilenames(),
                    'statePath' => $component->getStatePath(),
                    'types' => $component->getAcceptedFileTypes(),
                    'visibility' => $component->getVisibility(),
                    'keepOriginalSize' => $this->shouldKeepOriginalSize(),
                ]);
            });
    }
}
