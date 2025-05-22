<?php

namespace FinnWiel\ShazzooMedia\Models;


use Awcodes\Curator\Models\Media as CuratorMedia;
use Awcodes\Curator\Support\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShazzooMedia extends CuratorMedia
{

    protected $table = 'media';

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'curations' => 'array',
        'conversions' => 'array',
        'exif' => 'array',
    ];

    public function __get($key)
    {
        if (str_ends_with($key, '_url')) {
            $conversion = str_replace('_url', '', $key);
            return $this->getConversionUrl($conversion);
        }
        return parent::__get($key);
    }


    protected function getConversionUrl(string $conversion): string
    {
        $baseName = pathinfo($this->name, PATHINFO_FILENAME);
        $conversionConfig = config("shazzoo_media.conversions.{$conversion}", []);
        $ext = $conversionConfig['ext'] ?? config('shazzoo_media.conversion_ext', 'webp');
        $conversionPath = "media/{$this->id}/conversions/{$baseName}-{$conversion}.{$ext}";

        if (!Storage::disk('public')->exists($conversionPath)) {
            return $this->url;
        }

        return asset("storage/{$conversionPath}");
    }
}
