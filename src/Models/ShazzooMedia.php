<?php

namespace FinnWiel\ShazzooMedia\Models;

use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $appends = [
        'url',
        'full_path',
        'thumbnail_url',
        'medium_url',
        'large_url',
        'pretty_name',
        'size_for_humans',
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

        if (! Storage::disk('public')->exists($conversionPath)) {
            return $this->url;
        }

        return asset("storage/{$conversionPath}");
    }

    public function getSignedUrl(array $parameters = []): string
    {
        return Glide::getUrl($this->path, $parameters);
    }

    public function sizeForHumans(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => filled($this->size) ? Curator::sizeForHumans((int) $this->size) : null,
        );
    }
}
