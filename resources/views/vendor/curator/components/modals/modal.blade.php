<x-filament::modal id="curator-panel" width="7xl" class="curator-panel" displayClasses="block">
    <x-slot name="heading">
        {{ trans('curator::views.panel.heading') }}
    </x-slot>
    <div class="p-10 h-[85dvh] overflow-y-auto">
        <livewire:curator-panel />
    </div>
</x-filament::modal>
