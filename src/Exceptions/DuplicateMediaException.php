<?php

namespace FinnWiel\ShazzooMedia\Exceptions;

use Exception;

class DuplicateMediaException extends Exception
{
    public object $duplicate;

    public function __construct(object $duplicate)
    {
        parent::__construct(trans('shazzoo_media::notifications.exeptions.duplicate.message'));
        $this->duplicate = $duplicate;
    }

    public function getDuplicate(): object
    {
        return $this->duplicate;
    }
}
