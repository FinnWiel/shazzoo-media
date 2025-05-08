<?php

namespace FinnWiel\ShazzooMedia\Components\Forms;

use FinnWiel\ShazzooMedia\Models\MediaExtended;
use Awcodes\Curator\Components\Forms\CuratorPicker as CuratorPicker;
use Filament\Forms\Components\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Filament\Support\Colors\Color;

class ShazzooMediaPicker extends CuratorPicker
{
    protected static array $conversionRegistry = [];
    public bool $keepOriginalSize = false;

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
        // Iterate over each field and its conversions
        foreach (static::$conversionRegistry as $field => $conversions) {

            // Find all occurrences of the media ID for the current field (e.g., thumbnail_image)
            $mediaIds = static::findValuesInNestedArray($formData, $field);

            // If no media IDs are found, log a warning and continue
            if (empty($mediaIds)) {
                Log::warning("No media IDs found for field: {$field}");
                continue;
            }

            Log::info("Found media IDs for field: {$field}", [
                'media_ids' => $mediaIds,
            ]);
            // Process each media ID for the field
            foreach ($mediaIds as $mediaId) {
                Log::info($mediaId);
                $media = MediaExtended::find($mediaId);

                if (!$media) {
                    Log::warning("Media not found for ID: {$mediaId}");
                    continue;
                }

                $existingConversions = json_decode($media->conversions, true) ?? [];

                // Add only new conversions
                $newConversions = array_diff($conversions, $existingConversions);
                if (empty($newConversions)) {
                    Log::info("No new conversions to add for media ID: {$media->id}");
                    continue;
                }

                $mergedConversions = array_merge($existingConversions, $newConversions);

                $media->conversions = json_encode($mergedConversions);
                $media->save();

                Log::info("Conversions saved for media ID: {$media->id}", [
                    'new_conversions' => $newConversions,
                    'all_conversions' => $mergedConversions,
                ]);

                Artisan::call('media:conversions:generate', ['--id' => $media->id]);
            }
        }
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
            ->label('Select Image')
            ->button()
            ->size('md')
            ->color(Color::Amber)
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
