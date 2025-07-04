<div x-data="{
    isMac: navigator.platform.toUpperCase().includes('MAC'),
    handleItemClick(mediaId = null, event) {
        if (!mediaId) return;

        const isModifierHeld = event.metaKey || event.ctrlKey;

        if ($wire.isMultiple && isModifierHeld) {
            if (this.isSelected(mediaId)) {
                let toRemove = $wire.selected.find(obj => obj.id == mediaId);
                $wire.removeFromSelection(toRemove.id);
            } else {
                $wire.addToSelection(mediaId);
            }
            return;
        }

        if ($wire.selected.length === 1 && $wire.selected[0].id != mediaId) {
            $wire.removeFromSelection($wire.selected[0].id);
            $wire.addToSelection(mediaId);
            return;
        }

        if ($wire.selected.length === 1 && this.isSelected(mediaId)) {
            $wire.removeFromSelection(mediaId);
            return;
        }

        if (!this.isSelected(mediaId)) {
            $wire.addToSelection(mediaId);
        }
    },
    isSelected(mediaId = null) {
        if ($wire.selected.length === 0) return false;

        return Object.values($wire.selected).find(obj => obj.id == mediaId) !== undefined;
    },
}" class="curator-panel h-full absolute inset-0 flex flex-col">
    <!-- Toolbar -->
    <div
        class="curator-panel-toolbar px-4 py-2 flex items-center justify-between bg-gray-200/70 dark:bg-black/20 dark:text-white">
        <div class="flex items-center gap-2">
            <x-filament::button size="xs" color="gray" x-on:click="$wire.selected = []"
                x-show="$wire.selected.length > 1">
                {{ trans('curator::views.panel.deselect_all') }}
            </x-filament::button>

            @if ($isMultiple)
                <p class="text-xs" x-data="{ keyLabel: navigator.platform.toUpperCase().includes('MAC') ? 'Cmd' : 'Ctrl' }">
                    <span x-text="keyLabel"></span>
                    {{ trans('curator::views.panel.add_multiple_file', ['key' => '']) }}
                </p>
            @endif
        </div>

        <div class="w-full max-w-xs pl-8">
            <label
                class="border border-gray-300 dark:border-gray-700 rounded-lg relative flex items-center w-full max-w-xs">
                <span class="sr-only">{{ trans('curator::views.panel.search_label') }}</span>
                <x-filament::icon alias="curator::icons.check" icon="heroicon-s-magnifying-glass"
                    class="w-4 h-4 absolute top-1.5 left-2 rtl:left-0 rtl:right-2 dark:text-gray-500" />
                <input type="search" placeholder="{{ trans('curator::views.panel.search_placeholder') }}"
                    wire:model.live.debounce.500ms="search"
                    class="block w-full transition text-sm py-1 !ps-8 !pe-3 duration-75 border-none focus:ring-1 focus:ring-inset focus:ring-primary-600 disabled:opacity-70 bg-transparent placeholder-gray-700 dark:placeholder-gray-400 rounded-lg" />
                <svg fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                    class="animate-spin h-4 w-4 text-gray-400 dark:text-gray-500 sm absolute right-2" wire:loading.delay
                    wire:target="search">
                    <path clip-rule="evenodd"
                        d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                        fill-rule="evenodd" fill="currentColor" opacity="0.2" />
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="currentColor" />
                </svg>
            </label>
        </div>
    </div>
    <!-- End Toolbar -->

    <div class="flex-1 relative flex flex-col lg:flex-row overflow-hidden">
        <div wire:loading.delay wire:target="gotoPage"
            class="absolute inset-0 bg-white/60 dark:bg-black/60 z-50 flex items-center justify-center">
            <div class="animate-spin h-6 w-6 border-4 border-primary-500 border-t-transparent rounded-full m-10"></div>
        </div>
        <!-- Gallery -->
        <div class="curator-panel-gallery flex-1 h-full overflow-auto p-4">
            <ul class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                @forelse ($paginatedFiles as $file)
                    <li wire:key="media-{{ $file['id'] }}" class="relative aspect-square"
                        x-bind:class="{ 'opacity-40': $wire.selected.length > 0 && !isSelected('{{ $file['id'] }}') }">
                        <button type="button" x-on:click="handleItemClick('{{ $file['id'] }}', $event)"
                            class="block w-full h-full overflow-hidden bg-gray-700 rounded-lg">
                            @if (str_contains($file['type'], 'image'))
                                <img src="{{ $file['url'] }}" alt="{{ $file['alt'] ?? '' }}"
                                    class="block w-full object-contain h-full checkered rounded-lg" />
                            @else
                                <div
                                    class="curator-document-image grid place-items-center w-full h-full text-xs uppercase relative">
                                    <div class="relative grid place-items-center w-full h-full">
                                        @if (str_contains($file['type'], 'video'))
                                            <x-filament::icon alias="curator::icons.video-camera"
                                                icon="heroicon-o-video-camera" class="w-16 h-16 opacity-20" />
                                        @else
                                            <x-filament::icon alias="curator::icons.document" icon="heroicon-o-document"
                                                class="w-16 h-16 opacity-20" />
                                        @endif
                                    </div>
                                    <span class="block absolute">{{ $file['ext'] }}</span>
                                </div>
                            @endif
                        </button>

                        <p
                            class="text-xs truncate absolute bottom-0 inset-x-0 px-1 pb-1 pt-4 text-white bg-gradient-to-t from-black/80 to-transparent pointer-events-none">
                            {{ $file['pretty_name'] }}
                        </p>

                        <button type="button" x-on:click="handleItemClick('{{ $file['id'] }}', $event)"
                            x-show="isSelected('{{ $file['id'] }}')" x-cloak
                            class="absolute inset-0 flex items-center justify-center w-full h-full rounded-lg shadow text-primary-600 bg-primary-500/20 ring-4 ring-primary-500">
                            <span
                                class="flex items-center justify-center w-8 h-8 text-white rounded-full bg-primary-500 drop-shadow">
                                <x-filament::icon alias="curator::icons.check" icon="heroicon-s-check"
                                    class="w-4 h-4" />
                            </span>
                            <span class="sr-only">{{ trans('curator::views.panel.deselect') }}</span>
                        </button>
                    </li>
                @empty
                    <li class="col-span-3 sm:col-span-4 md:col-span-6 lg:col-span-8">
                        {{ trans('curator::views.panel.empty') }}
                    </li>
                @endforelse
            </ul>

            <div class="mt-4 flex justify-center">
                {{ $paginatedFiles->links() }}
            </div>


        </div>
        <!-- End Gallery -->

        <!-- Sidebar -->
        <div
            class="curator-panel-sidebar w-full lg:h-full lg:max-w-xs overflow-auto bg-gray-100 dark:bg-gray-900/30 flex flex-col shadow-top lg:shadow-none z-[1] rounded-b-xl">
            <div class="flex-1 overflow-hidden">
                <div class="flex flex-col h-full overflow-y-auto">
                    <h4 class="font-bold py-2 px-4 mb-0">
                        <span>
                            {{ count($selected) === 1 ? trans('curator::views.panel.edit_media') : trans('curator::views.panel.add_files') }}
                        </span>
                    </h4>

                    <div class="flex-1 px-4 pb-4">
                        <div class="h-full">

                            @if (count($selected) === 1)
                                @php
                                    $file = Arr::first($selected);
                                @endphp

                                @include('curator::components.forms.edit-preview', [
                                    'file' => $file,
                                    'actions' => array_filter([
                                        $this->viewAction(),
                                        $this->downloadAction(),
                                        str_starts_with($file['type'], 'image/') && !str_starts_with($file['type'], 'image/svg+xml') ? $this->convertAction() : null,
                                        $this->destroyAction(),
                                    ]),
                                ])
                            @endif

                            <div class="mb-4 mt-px">
                                {{ $this->form }}
                            </div>

                            <x-filament-actions::modals />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end mt-auto gap-3 py-3 px-4 border-t border-gray-300 bg-gray-200 dark:border-none dark:bg-zinc-800 rounded-xl sticky bottom-0">
                        @if (count($selected) !== 1)
                            <div>
                                {{ $this->addInsertFilesAction }}
                            </div>
                        @endif
                        @if (count($selected) === 1)
                            <div class="flex gap-3">
                                @if ($this->updateFileAction->isVisible())
                                    {{ $this->updateFileAction }}
                                @endif
                                {{ $this->cancelEditAction }}
                            </div>
                        @endif
                        @if (count($selected) > 0)
                            <div class="ml-auto">
                                {{ $this->insertMediaAction }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- End Sidebar -->
    </div>
</div>
