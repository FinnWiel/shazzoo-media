<?php

namespace FinnWiel\ShazzooMedia\Resources;

use Awcodes\Curator\Components\Forms\CuratorEditor;
use Awcodes\Curator\Resources\MediaResource as BaseMediaResource;
use Awcodes\Curator\Resources\MediaResource\CreateMedia;
use Awcodes\Curator\Resources\MediaResource\EditMedia;
use Awcodes\Curator\Resources\MediaResource\ListMedia;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Awcodes\Curator\is_media_resizable;

class MediaResource extends BaseMediaResource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.file'))
                            ->hiddenOn('edit')
                            ->schema([
                                static::getUploaderField()
                                    ->required()
                                    ->live()
                                    ->getUploadedFileNameForStorageUsing(function (Get $get, ShazzooMediaUploader $component, $file) {
                                        $name = $get('name');

                                        return ! empty($name) ? Str::slug($name) : $component->getSuggestedFileName($file);
                                    }),
                            ]),
                        Tabs::make('image')
                            ->hiddenOn('create')
                            ->tabs([
                                Tab::make(trans('curator::forms.sections.preview'))
                                    ->schema([
                                        ViewField::make('preview')
                                            ->view('curator::components.forms.preview')
                                            ->hiddenLabel()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function ($component, $state, $record) {
                                                $component->state($record);
                                            }),
                                    ]),
                                Tab::make(trans('curator::forms.sections.curation'))
                                    ->visible(fn($record) => is_media_resizable($record->type) && config('curator.tabs.display_curation'))
                                    ->schema([
                                        Repeater::make('curations')
                                            ->label(trans('curator::forms.sections.curation'))
                                            ->hiddenLabel()
                                            ->reorderable(false)
                                            ->itemLabel(fn($state): ?string => $state['curation']['key'] ?? null)
                                            ->collapsible()
                                            ->schema([
                                                CuratorEditor::make('curation')
                                                    ->hiddenLabel()
                                                    ->buttonLabel(trans('curator::forms.curations.button_label'))
                                                    ->required()
                                                    ->lazy(),
                                            ]),
                                    ]),
                                Tab::make(trans('curator::forms.sections.upload_new'))
                                    ->visible(config('curator.tabs.display_upload_new'))
                                    ->schema([
                                        static::getUploaderField()
                                            ->helperText(trans('curator::forms.sections.upload_new_helper')),
                                    ]),
                            ]),
                        Section::make(trans('curator::forms.sections.details'))
                            ->schema([
                                ViewField::make('details')
                                    ->view('curator::components.forms.details')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->columnSpan('full')
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        $component->state($record);
                                    }),
                            ]),
                        Section::make(trans('curator::forms.sections.exif'))
                            ->collapsed()
                            ->visible(fn($record) => $record && $record->exif)
                            ->schema([
                                KeyValue::make('exif')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->editableKeys(false)
                                    ->columnSpan('full'),
                            ]),
                    ])
                    ->columnSpan([
                        'md' => 'full',
                        'lg' => 2,
                    ]),
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.meta'))
                            ->schema(
                                static::getAdditionalInformationFormSchema()
                            ),
                    ])->columnSpan([
                        'md' => 'full',
                        'lg' => 1,
                    ]),
            ])->columns([
                'lg' => 3,
            ]);
    }
    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return $table
            ->columns(
                $livewire->layoutView === 'grid'
                    ? static::getDefaultGridTableColumns()
                    : static::getDefaultTableColumns(),
            )
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('placeholder')
                    ->label('No available actions')
                    ->disabled()
                    ->icon('heroicon-o-lock-closed')
                    ->visible(
                        fn(Model $record) => tap(
                            config('shazzoo_media.media_policies') &&
                                auth()->guard()->check() &&
                                !optional(auth()->guard()->user())->can('update', $record) &&
                                !optional(auth()->guard()->user())->can('delete', $record),
                            function ($result) use ($record) {
                                $user = auth()->guard()->user();
                            }
                        )
                    ),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->contentGrid(function () use ($livewire) {
                if ($livewire->layoutView === 'grid') {
                    return [
                        'md' => 2,
                        'lg' => 3,
                        'xl' => 4,
                    ];
                }

                return null;
            })
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([6, 12, 24, 48, 'all'])
            ->recordUrl(false);
    }

    public static function getUploaderField(): ShazzooMediaUploader
    {
        return ShazzooMediaUploader::make('file')
            ->acceptedFileTypes(config('curator.accepted_file_types'))
            ->directory(config('curator.directory'))
            ->disk(config('curator.disk'))
            ->hiddenLabel()
            ->minSize(config('curator.min_size'))
            ->maxFiles(1)
            ->maxSize(config('curator.max_size'))
            ->panelAspectRatio('24:9')
            ->pathGenerator(config('curator.path_generator'))
            ->preserveFilenames(config('curator.should_preserve_filenames'))
            ->visibility(config('curator.visibility'))
            ->storeFileNamesIn('originalFilename')
            ->image()
            ->imageEditor()
            ->imageEditorAspectRatios([
                null,
                '16:9',
                '4:3',
                '3:2',
                '1:1',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
