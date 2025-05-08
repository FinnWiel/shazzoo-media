<?php

namespace FinnWiel\ShazzooMedia\Components\Modals;

use FinnWiel\ShazzooMedia\Models\MediaExtended;
use Illuminate\View\View;
use Filament\Forms\Components\View as FormView;
use Awcodes\Curator\Components\Modals\CuratorPanel as BaseCuratorPanel;
use Awcodes\Curator\Resources\MediaResource;
use Exception;
use Filament\Forms\Components\Group;
use Filament\Forms\Form;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaUploader;
use Livewire\Attributes\On;

class ShazzooMediaPanel extends BaseCuratorPanel
{

    public array $files_to_add = [];
    public bool $keepOriginalSize = false;

    #[On('open-modal')]
    public function openModal(string $id, array $settings = []): void
    {
        if ($id !== 'curator-panel') {
            return;
        }
        $this->keepOriginalSize = $settings['keepOriginalSize'] ?? false;
        // dd($this->keepOriginalSize);
        parent::openModal($id, $settings);
    }

    /**
     * @var string[]
     */
    public function form(Form $form): Form
    {

        if ($this->maxItems) {
            $this->validationRules = array_filter($this->validationRules, function ($value) {
                if ($value === 'array' || str_starts_with($value, 'max:')) {
                    return false;
                }

                return true;
            });
        }

        return $form
            ->schema([
                ShazzooMediaUploader::make('files_to_add')
                    ->visible(function () {
                        return count($this->selected) !== 1 &&
                            (
                                is_null(Gate::getPolicyFor($this->mediaClass)) ||
                                Gate::allows('create', $this->mediaClass)
                            );
                    })
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '3:2',
                        '1:1',
                    ])
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
                    FormView::make('preview')
                        ->view('curator::components.forms.edit-preview', [
                            'file' => Arr::first($this->selected),
                            'actions' => [
                                $this->viewAction(),
                                $this->downloadAction(),
                                $this->destroyAction(),
                            ],
                        ]),
                    ...collect(App::make(MediaResource::class)->getAdditionalInformationFormSchema())
                        ->map(function ($field) {
                            return $field->disabled(function () {
                                $media = MediaExtended::find($this->selected)->first();
                                return !Gate::allows('update', $media);
                            });
                        })->toArray(),
                ])->visible(fn() => filled($this->selected) && count($this->selected) === 1),
            ])->statePath('data');
    }


    /**
     * 
     * @return Action
     */
    public function addInsertFilesAction(): Action
    {
        return $this->addFilesAction(true)
            ->name('addInsertFiles')
            ->color('primary')
            ->label('Insert image');
    }

    /**
     * 
     * @return Action
     */
    public function insertMediaAction(): Action
    {
        return Action::make('insertMedia')
            ->button()
            ->size('sm')
            ->color('primary')
            ->label('Use image')
            ->action(function (): void {
                $this->dispatch(
                    'insert-content',
                    type: 'media',
                    statePath: $this->statePath,
                    media: $this->selected
                );

                $this->dispatch('close-modal', id: $this->modalId ?? 'curator-panel');
            });
    }

    /**
     * 
     * @return Action
     */
    public function updateFileAction(): Action
    {
        return Action::make('updateFile')
            ->button()
            ->size('sm')
            ->color('secondary')
            ->label(trans('curator::views.panel.edit_save'))
            ->action(function (): void {
                try {
                    $item = $this->mediaClass->find(Arr::first($this->selected)['id']);
                    if ($item) {
                        $item->update($this->form->getState());

                        $this->selected = collect($this->selected)->map(function ($selectedItem) use ($item) {
                            if ($selectedItem['id'] === $item->id) {
                                return $item->refresh();
                            }

                            return $selectedItem;
                        })->toArray();

                        Notification::make('curator_update_success')
                            ->success()
                            ->body(trans('curator::notifications.update_success'))
                            ->send();
                    } else {
                        throw new Exception();
                    }
                } catch (Exception) {
                    Notification::make('curator_update_error')
                        ->danger()
                        ->body(trans('curator::notifications.update_error'))
                        ->send();
                }
            });
    }

    /**
     * 
     * @return View
     */
    public function render(): View
    {
        return view('curator::components.modals.curator-panel');
    }
}
