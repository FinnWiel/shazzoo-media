<?php

namespace FinnWiel\ShazzooMedia\Traits;

trait HandlesConversions
{
    protected function mutateFormDataBeforeSave(array $data): array
    {
        //Save the conversions to the media
        \FinnWiel\ShazzooMedia\Components\Forms\CustomCuratorPicker::saveConversionsToMedia($data);

        return $data;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        //Save the conversions to the media
        \FinnWiel\ShazzooMedia\Components\Forms\CustomCuratorPicker::saveConversionsToMedia($data);

        return $data;
    }
}
