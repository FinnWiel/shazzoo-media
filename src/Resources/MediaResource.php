<?php

namespace FinnWiel\ShazzooMedia\Resources;

use Awcodes\Curator\Resources\Media\MediaResource as BaseMediaResource;
use Awcodes\Curator\Resources\Media\Schemas\MediaForm as CuratorMediaForm;
use Awcodes\Curator\Resources\Media\Tables\MediaTable as CuratorMediaTable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Get;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaUploader;
use FinnWiel\ShazzooMedia\Resources\MediaResource\CreateMedia;
use FinnWiel\ShazzooMedia\Resources\MediaResource\EditMedia;
use FinnWiel\ShazzooMedia\Resources\MediaResource\ListMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaResource extends BaseMediaResource
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        Section::make(trans('curator::forms.sections.preview'))
                            ->schema([
                                ViewField::make('preview')
                                    ->view('curator::components.forms.preview')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        $component->state($record);
                                    }),
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
                            ->visible(fn ($record) => $record && $record->exif)
                            ->schema([
                                KeyValue::make('exif')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->editableKeys(false)
                                    ->afterStateHydrated(function (KeyValue $component, $state, $record): void {
                                        $exifData = is_array($record?->exif) ? $record->exif : [];

                                        $component->state(collect($exifData)
                                            ->map(fn ($value) => is_scalar($value) || is_null($value)
                                                ? $value
                                                : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                                            ->all());
                                    })
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
                    ])
                    ->columnSpan([
                        'md' => 'full',
                        'lg' => 1,
                    ]),
            ])
            ->columns([
                'lg' => 3,
            ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return $table
            ->columns(
                $livewire->layoutView === 'grid'
                    ? static::getDefaultGridTableColumns()
                    : static::getDefaultTableColumns()
            )
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('placeholder')
                    ->label('No available actions')
                    ->disabled()
                    ->icon('heroicon-o-lock-closed')
                    ->visible(
                        fn (Model $record) => config('shazzoo_media.media_policies') &&
                            auth()->guard()->check() &&
                            ! optional(auth()->guard()->user())->can('update', $record) &&
                            ! optional(auth()->guard()->user())->can('delete', $record)
                    ),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
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

    /**
     * @throws \Exception
     */
    public static function getDefaultTableColumns(): array
    {
        return CuratorMediaTable::getDefaultTableColumns();
    }

    /**
     * @throws \Exception
     */
    public static function getDefaultGridTableColumns(): array
    {
        return CuratorMediaTable::getDefaultGridTableColumns();
    }

    public static function getUploaderField(): ShazzooMediaUploader
    {
        return ShazzooMediaUploader::make('file')
            ->acceptedFileTypes(config('shazzoo_media.accepted_file_types', []))
            ->directory(config('shazzoo_media.directory', 'media'))
            ->disk(config('curator.default_disk'))
            ->hiddenLabel()
            ->minSize(config('shazzoo_media.min_size', 0))
            ->maxFiles(1)
            ->maxSize(config('shazzoo_media.max_size', 51200))
            ->panelAspectRatio('24:9')
            ->pathGenerator(config('curator.path_generator'))
            ->preserveFilenames(config('curator.features.preserve_file_names', false))
            ->visibility(config('curator.default_visibility', 'public'))
            ->storeFileNamesIn('originalFilename')
            ->imageEditor()
            ->imageEditorAspectRatios([
                null,
                '16:9',
                '4:3',
                '3:2',
                '1:1',
            ])
            ->formatStateUsing(fn ($state) => is_array($state) && isset($state['path']) ? $state['path'] : $state);
    }

    /**
     * @throws \Exception
     */
    public static function getAdditionalInformationFormSchema(): array
    {
        return CuratorMediaForm::getAdditionalInformationFormSchema();
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
