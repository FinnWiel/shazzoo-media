<div class="relative h-[85dvh] overflow-hidden">
    <livewire:curator-panel
        :settings="$settings"
        @insert-media.window="callSchemaComponentMethod('{{ $key }}', 'updateState', $event.detail); close()"
        @insertMedia.window="callSchemaComponentMethod('{{ $key }}', 'updateState', $event.detail); close()"
    />
</div>
