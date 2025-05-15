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

class MediaExtended extends CuratorMedia
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
        // Check if the requested key ends with "_url"
        if (str_ends_with($key, '_url')) {
            $conversion = str_replace('_url', '', $key);

            // Dynamically return the conversion URL
            return $this->getConversionUrl($conversion);
        }

        // Fallback to the parent __get method for other attributes
        return parent::__get($key);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $config = config('shazzoo_media.tenant_scoping');

            if ($config['enabled']) {
                $resolver = $config['resolver'];
                $tenantId = $resolver();

                if (!is_null($tenantId)) {
                    $builder->where($config['field'], $tenantId);
                }
            }
        });
    }

    public function save(array $options = [])
    {
        $config = config('shazzoo_media.tenant_scoping') ?? [];

        if (!empty($config['enabled'])) {
            $field = $config['field'] ?? 'tenant_id';
            $resolver = $config['resolver'] ?? fn() => null;

            if (empty($this->{$field})) {
                $tenantId = $resolver();

                if (!is_null($tenantId)) {
                    $this->{$field} = $tenantId;
                }
            }
        }

        return parent::save($options);
    }


    protected function getConversionUrl(string $conversion): string
    {
        $baseName = pathinfo($this->name, PATHINFO_FILENAME);
        $ext = config('shazzoo_media.conversion_ext', 'webp');
        $conversionPath = "conversions/{$baseName}/{$baseName}-{$conversion}.{$ext}";

        if (!Storage::disk('public')->exists($conversionPath)) {
            return $this->url;
        }

        return asset("storage/{$conversionPath}");
    }
}
