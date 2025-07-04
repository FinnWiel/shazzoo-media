@props([
    'file' => null,
    'actions' => [],
])

@php
    if (is_array($actions)) {
        $actions = array_filter(
            $actions,
            fn ($action): bool => $action->isVisible(),
        );
    }
@endphp

@if ($file)
    <div class="flex justify-center overflow-hidden border border-gray-300 rounded dark:border-gray-700 checkered h-48 flex-shrink-0 relative mb-4">
        @if (str($file['type'])->contains('image'))
            <img
                src="{{ $file['url'] }}"
                alt="{{ $file['alt'] ?? '' }}"
                width="{{ $file['width'] }}"
                height="{{ $file['height'] }}"
                loading="lazy"
                class="overflow-hidden h-full w-auto dark:border-gray-900 checkered"
            />
        @elseif (str($file['type'])->contains('video'))
            <video controls src="{{ $file['url'] }}"></video>
        @else
            <x-curator::document-image
                label="{{ $file['name'] }}"
                icon-size="sm"
                class="p-4 rounded"
                :type="$file['type']"
                :extension="$file['ext']"
            />
        @endif

        <div class="absolute top-0 right-0 flex bg-gray-900 divide-x divide-gray-700 rounded-bl-lg shadow-md">
            @foreach ($actions as $action)
                {{ ($action)(['item' => $file]) }}
            @endforeach
        </div>
    </div>
@endif
