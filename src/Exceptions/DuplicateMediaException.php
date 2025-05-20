<?php

namespace FinnWiel\ShazzooMedia\Exceptions;

use Exception;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;

class DuplicateMediaException extends Exception
{
    public ShazzooMedia $duplicate;

    public function __construct(ShazzooMedia $duplicate)
    {
        parent::__construct(trans('shazzoo_media::notifications.exeptions.duplicate.message'));
        $this->duplicate = $duplicate;
    }

    public function getDuplicate(): ShazzooMedia
    {
        return $this->duplicate;
    }
}
