<?php

namespace FinnWiel\ShazzooMedia\Components\Modals;

use Awcodes\Curator\Components\Modals\CuratorPanel as BaseCuratorPanel;
use Awcodes\Curator\Resources\Media\MediaResource;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaUploader;
use FinnWiel\ShazzooMedia\Exceptions\DuplicateMediaException;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\WithPagination;

class ShazzooMediaPanel extends BaseCuratorPanel
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public int $page = 1;

    protected $queryString = [];

    public array $files_to_add = [];

    public bool $keepOriginalSize = false;

    public function mount(): void
    {
        if (($this->settings['directory'] ?? null) === null) {
            $this->settings['directory'] = config('shazzoo_media.directory', 'media');
        }

        parent::mount();

        $this->setMediaForm();

        if (blank($this->directory)) {
            $this->directory = config('shazzoo_media.directory', 'media');
        }
    }

    /**
     * @var string[]
     */
    #[On('open-modal')]
    public function openModal(string $id, array $settings = []): void
    {
        if ($id !== 'curator-panel') {
            return;
        }

        // Only include what you actually use
        $this->keepOriginalSize = $settings['keepOriginalSize'] ?? false;
        $this->acceptedFileTypes = $settings['acceptedFileTypes'] ?? [];
        $this->defaultSort = $settings['defaultSort'] ?? 'desc';
        $this->directory = $settings['directory'] ?? 'media';
        $this->diskName = $settings['diskName'] ?? 'public';
        $this->imageCropAspectRatio = $settings['imageCropAspectRatio'] ?? null;
        $this->imageResizeMode = $settings['imageResizeMode'] ?? null;
        $this->imageResizeTargetWidth = $settings['imageResizeTargetWidth'] ?? null;
        $this->imageResizeTargetHeight = $settings['imageResizeTargetHeight'] ?? null;
        $this->isLimitedToDirectory = $settings['isLimitedToDirectory'] ?? false;
        $this->isMultiple = $settings['isMultiple'] ?? false;
        $this->isTenantAware = $settings['isTenantAware'] ?? true;
        $this->tenantOwnershipRelationshipName = $settings['tenantOwnershipRelationshipName'] ?? null;
        $this->maxItems = $settings['maxItems'] ?? null;
        $this->maxSize = $settings['maxSize'] ?? null;
        $this->maxWidth = $settings['maxWidth'] ?? null;
        $this->minSize = $settings['minSize'] ?? null;
        $this->pathGenerator = $settings['pathGenerator'] ?? null;
        $this->validationRules = $settings['rules'] ?? [];
        $this->selected = (array) ($settings['selected'] ?? []);
        $this->shouldPreserveFilenames = $settings['shouldPreserveFilenames'] ?? false;
        $this->statePath = $settings['statePath'] ?? null;
        $this->types = $settings['types'] ?? [];
        $this->visibility = $settings['visibility'] ?? 'public';

        $this->setMediaForm();
    }

    /**
     * @var string[]
     */
    public function form(Schema $schema): Schema
    {
        if ($this->maxItems) {
            $this->validationRules = array_filter($this->validationRules, function ($value) {
                return ! ($value === 'array' || str_starts_with($value, 'max:'));
            });
        }

        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);

        return $schema
            ->schema([
                ShazzooMediaUploader::make('files_to_add')
                    ->visible(function () {
                        $modelClass = $this->getMediaModelClass();

                        return count($this->selected) !== 1 &&
                            (
                                is_null(Gate::getPolicyFor($modelClass)) ||
                                Gate::allows('create', $modelClass)
                            );
                    })
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([null, '16:9', '4:3', '3:2', '1:1'])
                    ->hiddenLabel()
                    ->required()
                    ->multiple()
                    ->label(trans('curator::forms.fields.file'))
                    ->preserveFilenames($this->shouldPreserveFilenames)
                    ->maxWidth($this->maxWidth)
                    ->minSize($this->minSize)
                    ->maxSize($this->maxSize)
                    ->rules($this->validationRules)
                    ->acceptedFileTypes($this->acceptedFileTypes)
                    ->disk($this->diskName)
                    ->visibility($this->visibility)
                    ->directory($this->directory)
                    ->pathGenerator($this->pathGenerator)
                    ->storeFileNamesIn('originalFilenames')
                    ->keepOriginalSize($this->keepOriginalSize),
                Group::make([
                    ...collect(App::make(MediaResource::class)->getAdditionalInformationFormSchema())
                        ->map(function ($field) use ($modelClass) {
                            return $field->disabled(function () use ($modelClass) {
                                // If policies are disabled, default to enabled fields
                                if (! config('shazzoo_media.media_policies')) {
                                    return false; // Fields are enabled
                                }

                                $first = Arr::first($this->selected);
                                if (is_array($first) && isset($first['id'])) {
                                    $media = $modelClass::find($first['id']);

                                    return ! Gate::allows('update', $media);
                                }

                                return true; // Default to disabled if no selection
                            });
                        })->toArray(),
                ])->visible(fn () => filled($this->selected) && count($this->selected) === 1),
            ])->statePath('panelData');
    }

    public function addInsertFilesAction(): Action
    {
        return $this->addFilesAction(true)
            ->name('addInsertFiles')
            ->color('primary')
            ->button()
            ->size('sm')
            ->icon(null)
            ->label(trans('shazzoo_media::views.panel.buttons.insert'));
    }

    public function insertMediaAction(): Action
    {
        return Action::make('insertMedia')
            ->button()
            ->size('sm')
            ->color('primary')
            ->icon(null)
            ->label(trans('shazzoo_media::views.panel.buttons.use'))
            ->action(function (): void {
                $this->dispatch('insert-media', [
                    'statePath' => $this->statePath,
                    'media' => $this->selected,
                    'context' => $this->context,
                ]);
                $this->dispatch('close-modal', id: $this->modalId ?? 'curator-panel');
            });
    }

    public function updateFileAction(): Action
    {
        return Action::make('updateFile')
            ->button()
            ->size('sm')
            ->color('secondary')
            ->icon(null)
            ->label(trans('curator::views.panel.edit_save'))
            ->action(function (): void {
                try {
                    $modelClass = $this->getMediaModelClass();
                    $item = $modelClass::find(Arr::first($this->selected)['id']);

                    if ($item) {
                        $item->update($this->form->getState());

                        $this->selected = collect($this->selected)->map(function ($selectedItem) use ($item) {
                            return $selectedItem['id'] === $item->id
                                ? $item->refresh()
                                : $selectedItem;
                        })->toArray();

                        Notification::make('curator_update_success')
                            ->success()
                            ->body(trans('curator::notifications.update_success'))
                            ->send();
                    } else {
                        throw new Exception;
                    }
                } catch (Exception) {
                    Notification::make('curator_update_error')
                        ->danger()
                        ->body(trans('curator::notifications.update_error'))
                        ->send();
                }
            });
    }

    public function cancelEditAction(): Action
    {
        return Action::make('cancelEdit')
            ->button()
            ->size('sm')
            ->color('gray')
            ->icon(null)
            ->label(trans('curator::views.panel.edit_cancel'))
            ->action(function (): void {
                $this->dispatch('close-modal', id: $this->modalId ?? 'curator-panel');
            });
    }

    public function addFilesAction(bool $insertAfter = false): Action
    {
        return Action::make('addFiles')
            ->button()
            ->size('sm')
            ->color('primary')
            ->icon(null)
            ->label(trans('shazzoo_media::views.panel.buttons.insert'))
            ->disabled(fn (): bool => count($this->form->getRawState()['files_to_add'] ?? []) === 0)
            ->visible(fn () => true)
            ->action(function () use ($insertAfter): void {
                try {
                    $media = $this->createMediaFiles();

                    $this->form->fill();
                    // $this->files = [...$media, ...$this->files];

                    if ($insertAfter) {
                        $this->dispatch('insert-media', [
                            'statePath' => $this->statePath,
                            'media' => $media,
                            'context' => $this->context,
                        ]);
                        $this->dispatch('close-modal', id: $this->modalId ?? 'curator-panel');

                        return;
                    }

                    foreach ($media as $item) {
                        $this->addToSelection($item['id']);
                    }
                } catch (DuplicateMediaException $e) {
                    $existingMedia = $e->getDuplicate();
                    Notification::make('upload_failed')
                        ->title(trans('shazzoo_media::notifications.exeptions.duplicate.title'))
                        ->body($e->getMessage())
                        ->warning()
                        ->send();

                    $this->selected = [];
                    $this->addToSelection($existingMedia->id);

                    $this->insertMediaAction()->call();
                }
            });
    }

    protected function createMediaFiles(): array
    {
        $media = [];
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);
        $formData = $this->form->getState();

        foreach ($formData['files_to_add'] as $item) {
            if (! empty($item['exif'])) {
                array_walk_recursive($item['exif'], function (&$entry) {
                    if (! mb_detect_encoding($entry, 'utf-8', true)) {
                        $entry = mb_convert_encoding($entry, 'utf-8');
                    }
                });
            }

            $item['title'] = pathinfo($formData['originalFilenames'][$item['path']] ?? null, PATHINFO_FILENAME);

            $model = new $modelClass($item);
            $model->file = $item;
            $model->save();

            $media[] = tap($model, fn ($media) => $media->getPrettyName())->toArray();
        }

        return $media;
    }

    /**
     * Set the media form based on the selected item.
     */
    public function setMediaForm(): void
    {
        $first = Arr::first($this->selected);

        if (is_array($first) && isset($first['id'])) {
            $modelClass = $this->getMediaModelClass();
            $item = $modelClass::find($first['id']);

            if ($item) {
                $this->form->fill($item->toArray());

                return;
            }
        }

        $this->form->fill();
    }

    /**
     * Add a media item to the selection.
     *
     * @param  int|string  $id  The ID of the media item to add.
     */
    public function addToSelection(int|string $id): void
    {
        $modelClass = $this->getMediaModelClass();
        $item = $modelClass::find($id);

        if (! $item) {
            return;
        }

        $itemArray = $item->toArray();

        if ($this->isMultiple) {
            if (! collect($this->selected)->contains('id', $itemArray['id'])) {
                $this->selected[] = $itemArray;
            }
        } else {
            $this->selected = [$itemArray];
        }

        $this->context = count($this->selected) === 1 ? 'edit' : 'create';
        $this->setMediaForm();
    }

    /**
     * Remove a media item from the selection.
     *
     * @param  int|string  $id  The ID of the media item to remove.
     */
    public function removeFromSelection(int|string $id): void
    {
        $this->selected = collect($this->selected)
            ->filter(fn ($item) => isset($item['id']) && $item['id'] != $id)
            ->values()
            ->all();

        $this->context = count($this->selected) === 1 ? 'edit' : 'create';
        $this->setMediaForm();
    }

    public function convertAction(): Action
    {
        return Action::make('convert')
            ->icon('heroicon-o-arrows-pointing-in')
            ->iconButton()
            ->size(Size::Large)
            ->iconSize(IconSize::Large)
            ->color('gray')
            ->extraAttributes([
                'style' => 'min-width: 2.25rem; min-height: 2.25rem;',
            ])
            ->form([
                Select::make('conversion')
                    ->label('Select Conversion')
                    ->options($this->getConversionOptions())
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $conversion = $data['conversion'];
                $mediaItem = $arguments['item'] ?? null;

                if (! $mediaItem) {
                    Notification::make()->danger()->title('No media selected')->send();

                    return;
                }

                $mediaId = $mediaItem['id'];

                // Call the Artisan command directly with options
                Artisan::call('media:conversions:set-db', [
                    '--id' => $mediaId,
                    '--conversion' => $conversion,
                    '--append' => true,
                ]);

                Artisan::call('media:conversions:generate', [
                    '--id' => $mediaId,
                    '--only' => $conversion,
                ]);

                Notification::make()->success()->title("Conversion '$conversion' added")->send();
            });
    }

    public function viewItemAction(): Action
    {
        return parent::viewItemAction()
            ->iconButton()
            ->size(Size::Large)
            ->iconSize(IconSize::Large)
            ->extraAttributes([
                'style' => 'min-width: 2.25rem; min-height: 2.25rem;',
            ]);
    }

    public function downloadItemAction(): Action
    {
        return parent::downloadItemAction()
            ->iconButton()
            ->size(Size::Large)
            ->iconSize(IconSize::Large)
            ->extraAttributes([
                'style' => 'min-width: 2.25rem; min-height: 2.25rem;',
            ]);
    }

    public function destroyItemAction(): Action
    {
        return parent::destroyItemAction()
            ->iconButton()
            ->size(Size::Large)
            ->iconSize(IconSize::Large)
            ->extraAttributes([
                'style' => 'min-width: 2.25rem; min-height: 2.25rem;',
            ]);
    }

    protected function getConversionOptions(): array
    {
        $conversions = config('shazzoo_media.conversions', []);

        return collect($conversions)->mapWithKeys(function ($settings, $name) {
            return [$name => ucfirst($name)];
        })->toArray();
    }

    /**
     * Get paginated files based on search criteria.
     *
     * @return LengthAwarePaginator
     */
    public function getPaginatedFiles()
    {
        $modelClass = config('shazzoo_media.model', ShazzooMedia::class);

        return $modelClass::query()
            ->whereNull('model_type')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when(! empty($this->types), fn ($query) => $query->whereIn('type', $this->types))
            ->orderBy('created_at', 'desc')
            ->paginate(config('shazzoo_media.pagination', 25), ['*'], 'page', $this->page);
    }

    /**
     * Get the view for pagination.
     */
    public function paginationView(): string
    {
        return 'shazzoo_media::livewire.simple-pagination';
    }

    /**
     * Go to a specific page in the pagination.
     *
     * @param  int  $page  The page number to go to.
     * @param  string  $pageName  The name of the page parameter (default is 'page').
     */
    public function gotoPage($page, $pageName = 'page')
    {
        $this->page = $page;
    }

    public function render(): View
    {
        return view('curator::livewire.curator-panel', [
            'paginatedFiles' => $this->getPaginatedFiles(),
        ]);
    }

    protected function getMediaModelClass(): string
    {
        return config('shazzoo_media.model', ShazzooMedia::class);
    }
}
