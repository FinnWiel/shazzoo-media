<?php

namespace FinnWiel\ShazzooMedia\Exceptions;

use Exception;
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;

class DuplicateMediaException extends Exception
{
    public ShazzooMedia $duplicate;

    public function __construct(ShazzooMedia $duplicate)
    {
        parent::__construct('Duplicate media detected. We have selected the duplicate for you.');
        $this->duplicate = $duplicate;
    }

    public function getDuplicate(): ShazzooMedia
    {
        return $this->duplicate;
    }
}
