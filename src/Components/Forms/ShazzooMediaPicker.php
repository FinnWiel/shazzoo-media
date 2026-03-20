<?php

namespace FinnWiel\ShazzooMedia\Components\Forms;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ShazzooMediaPicker extends CuratorPicker
{
    protected static array $conversionRegistry = [];

    public bool $keepOriginalSize = false;

    protected bool $onlySvg = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (CuratorPicker $component): Action => $component->getDownloadAction(),
            fn (CuratorPicker $component): Action => $this->getEditAction(),
            fn (CuratorPicker $component): Action => $component->getRemoveAction(),
            fn (CuratorPicker $component): Action => $component->getRemoveAllAction(),
            fn (CuratorPicker $component): Action => $component->getReorderAction(),
            fn (CuratorPicker $component): Action => $component->getViewAction(),
            fn (CuratorPicker $component): Action => $component->getPickerAction(),
        ]);
    }

    public function getEditAction(): Action
    {
        return Action::make('edit')
            ->label(trans('curator::views.picker.edit'))
            ->icon(Heroicon::Pencil)
            ->color('gray')
            ->hidden(fn (CuratorPicker $component): bool => $component->isDisabled())
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalCloseButton(true)
            ->modalContent(function (CuratorPicker $component, array $arguments): View {
                $directory = $component->getDirectory() ?? config('shazzoo_media.directory', 'media');
                $selected = (array) $component->getState();

                if (isset($arguments['id'])) {
                    $modelClass = config('shazzoo_media.model', ShazzooMedia::class);
                    $selectedMedia = $modelClass::query()->find($arguments['id']);

                    if ($selectedMedia) {
                        $selected = [
                            (string) Str::uuid() => $selectedMedia->toArray(),
                        ];
                    }
                }

                return view('curator::components.modals.curator-panel', [
                    'key' => $component->getKey(),
                    'settings' => [
                        'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                        'defaultSort' => $component->getDefaultPanelSort(),
                        'directory' => $directory,
                        'diskName' => $component->getDiskName(),
                        'imageCropAspectRatio' => $component->getImageCropAspectRatio(),
                        'imageResizeMode' => $component->getImageResizeMode(),
                        'imageResizeTargetWidth' => $component->getImageResizeTargetWidth(),
                        'imageResizeTargetHeight' => $component->getImageResizeTargetHeight(),
                        'isLimitedToDirectory' => $component->isLimitedToDirectory(),
                        'isTenantAware' => $component->isTenantAware(),
                        'tenantOwnershipRelationshipName' => $component->getTenantOwnershipRelationshipName(),
                        'isMultiple' => $component->isMultiple(),
                        'maxItems' => $component->getMaxItems(),
                        'maxSize' => $component->getMaxSize(),
                        'maxWidth' => $component->getMaxWidth(),
                        'minSize' => $component->getMinSize(),
                        'pathGenerator' => $component->getPathGenerator(),
                        'rules' => $component->getValidationRules(),
                        'selected' => $selected,
                        'shouldPreserveFilenames' => $component->shouldPreserveFilenames(),
                        'statePath' => $component->getStatePath(),
                        'types' => $component->getAcceptedFileTypes(),
                        'visibility' => $component->getVisibility(),
                        'keepOriginalSize' => $this->shouldKeepOriginalSize(),
                    ],
                ]);
            })
            ->action(fn (): null => null);
    }

    #[ExposedLivewireMethod]
    public function updateState(array $arguments): void
    {
        if (isset($arguments[0]) && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }

        $media = $arguments['media'] ?? [];

        if (is_array($media) && Arr::isAssoc($media) && array_key_exists('id', $media)) {
            $media = [$media];
        }

        $items = [];

        $state = array_values(is_array($media) ? $media : []);

        foreach ($state as $itemData) {
            if (! is_array($itemData)) {
                continue;
            }

            if (! array_key_exists('id', $itemData)) {
                continue;
            }

            $items[(string) Str::uuid()] = $itemData;
        }

        $this->state($items);
    }

    /**
     * Register conversions for the field.
     *
     * @param  array  $data  The conversions to register.
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
     * @param  string  $field  The field name to get conversions for.
     * @return array The conversions associated with the field.
     */
    public static function getConversionsFor(string $field): array
    {
        return static::$conversionRegistry[$field] ?? [];
    }

    /**
     * Save conversions to media based on the provided form data.
     *
     * @param  array  $formData  The form data containing media IDs.
     */
    public static function saveConversionsToMedia(array $formData): void
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);

        foreach (static::$conversionRegistry as $field => $conversions) {
            $mediaIds = static::findValuesInNestedArray($formData, $field);

            if (empty($mediaIds)) {
                continue;
            }

            foreach ($mediaIds as $mediaId) {
                $media = $modelClass::find($mediaId);

                if (! $media) {
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
     * @param  string|array|null  $types  The file type group(s) to accept.
     */
    public function fileType(string|array|null $types = null): static
    {
        if (is_null($types)) {
            $this->acceptedFileTypes([]);

            return $this;
        }

        $types = (array) $types;

        $groupMap = [
            'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'icon' => ['image/svg+xml'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-powerpoint',
            ],
            'video' => ['video/mp4', 'video/quicktime'],
            'audio' => ['audio/mpeg', 'audio/wav'],
            'flash' => ['application/x-shockwave-flash'],
            'all' => [],
        ];

        if (in_array('all', $types, true)) {
            $this->acceptedFileTypes([]); // Accept all

            return $this;
        }

        $accepted = collect($types)
            ->flatMap(fn ($type) => $groupMap[$type] ?? [])
            ->unique()
            ->values()
            ->all();

        $this->acceptedFileTypes($accepted);

        return $this;
    }

    /**
     * Helper method to search for values in a nested array by key.
     *
     * @param  array  $data  The array to search.
     * @param  string  $key  The key to search for.
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
     * @param  string  $statePath  The state path to set.
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
     */
    public function getPickerAction(): Action
    {
        return Action::make('launchPanel')
            ->label(trans('shazzoo_media::views.picker.select'))
            ->button()
            ->size('md')
            ->color('primary')
            ->icon('heroicon-s-photo')
            ->outlined(true)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalCloseButton(true)
            ->modalContent(function (CuratorPicker $component): View {
                $directory = $component->getDirectory() ?? config('shazzoo_media.directory', 'media');

                return view('curator::components.modals.curator-panel', [
                    'key' => $component->getKey(),
                    'settings' => [
                        'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                        'defaultSort' => $component->getDefaultPanelSort(),
                        'directory' => $directory,
                        'diskName' => $component->getDiskName(),
                        'imageCropAspectRatio' => $component->getImageCropAspectRatio(),
                        'imageResizeMode' => $component->getImageResizeMode(),
                        'imageResizeTargetWidth' => $component->getImageResizeTargetWidth(),
                        'imageResizeTargetHeight' => $component->getImageResizeTargetHeight(),
                        'isLimitedToDirectory' => $component->isLimitedToDirectory(),
                        'isTenantAware' => $component->isTenantAware(),
                        'tenantOwnershipRelationshipName' => $component->getTenantOwnershipRelationshipName(),
                        'isMultiple' => $component->isMultiple(),
                        'maxItems' => $component->getMaxItems(),
                        'maxSize' => $component->getMaxSize(),
                        'maxWidth' => $component->getMaxWidth(),
                        'minSize' => $component->getMinSize(),
                        'pathGenerator' => $component->getPathGenerator(),
                        'rules' => $component->getValidationRules(),
                        'selected' => (array) $component->getState(),
                        'shouldPreserveFilenames' => $component->shouldPreserveFilenames(),
                        'statePath' => $component->getStatePath(),
                        'types' => $component->getAcceptedFileTypes(),
                        'visibility' => $component->getVisibility(),
                        'keepOriginalSize' => $this->shouldKeepOriginalSize(),
                    ],
                ]);
            })
            ->action(fn (): null => null);
    }
}
