<?php

namespace FinnWiel\ShazzooMedia\Exceptions;

use Exception;
use FinnWiel\ShazzooMedia\Models\MediaExtended;

class DuplicateMediaException extends Exception
{
    public MediaExtended $duplicate;

    public function __construct(MediaExtended $duplicate)
    {
        parent::__construct('Duplicate media detected. We have selected the duplicate for you.');
        $this->duplicate = $duplicate;
    }

    public function getDuplicate(): MediaExtended
    {
        return $this->duplicate;
    }
}
