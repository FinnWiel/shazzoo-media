<?php

namespace FinnWiel\ShazzooMedia\Tests\Unit;

use FinnWiel\ShazzooMedia\Components\Forms\ShazzooMediaPicker;
use FinnWiel\ShazzooMedia\Tests\TestCase;

class ShazzooMediaPickerTest extends TestCase
{
    public function test_file_type_image_maps_to_supported_image_mime_types(): void
    {
        $picker = ShazzooMediaPicker::make('featured_image_id')
            ->fileType('image');

        $this->assertSame([
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], $picker->getAcceptedFileTypes());
    }

    public function test_file_type_all_clears_the_accepted_mime_type_filter(): void
    {
        $picker = ShazzooMediaPicker::make('featured_image_id')
            ->fileType(['image', 'all']);

        $this->assertSame([], $picker->getAcceptedFileTypes());
    }

    public function test_picker_action_uses_curator_v5_launch_panel_action_name(): void
    {
        $picker = ShazzooMediaPicker::make('featured_image_id');

        $this->assertSame('launchPanel', $picker->getPickerAction()->getName());
    }
}
