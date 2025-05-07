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


    protected static function booted()
    {
        static::creating(function ($media) {
            if (Auth::check() && ! $media->tenant_id) {
                $media->tenant_id = Auth::user()->tenant_id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (!config('shazzoo_media.enable_tenant_scope', true)) {
                return;
            }
        
            if (Auth::check()) {
                $user = Auth::user();
        
                if ($user->hasRole('superadmin')) {
                    return;
                }
        
                $builder->where(function ($query) use ($user) {
                    $query->where('tenant_id', $user->tenant_id)
                          ->orWhereNull('tenant_id'); // shared media
                });
            }
        });
        
    }

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

    protected function getConversionUrl(string $conversion): string
    {
        $conversionPath = "conversions/{$this->name}/{$this->name}-{$conversion}.webp";

        if (!Storage::disk('public')->exists($conversionPath)) {
            return $this->url;
        } else {
            return asset("storage/{$conversionPath}");
        }
    }

}