<?php

namespace FinnWiel\ShazzooMedia\Models;

use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Support\Facades\Storage;

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
        $filename = pathinfo(basename($this->path ?? $this->url), PATHINFO_FILENAME);
        $conversionConfig = config("shazzoo_media.conversions.{$conversion}", []);
        $ext = $conversionConfig['ext'] ?? config('shazzoo_media.conversion_ext', 'webp');
        $conversionPath = "media/{$this->id}/conversions/{$filename}-{$conversion}.{$ext}";

        if (!Storage::disk('public')->exists($conversionPath)) {
            return $this->url;
        }

        return asset("storage/{$conversionPath}");
    }
}
