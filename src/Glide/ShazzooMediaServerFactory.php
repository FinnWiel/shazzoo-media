<?php

namespace FinnWiel\ShazzooMedia\Glide;

use Awcodes\Curator\Glide\Contracts\ServerFactory;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;
use League\Glide\ServerFactory as GlideServerFactory;

class ShazzooMediaServerFactory implements ServerFactory
{
    public function getFactory(): GlideServerFactory | Server
    {
        $server = GlideServerFactory::create([
            'driver' => 'gd',
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => storage_path('app'),
            'source_path_prefix' => 'public',
            'cache' => storage_path('app'),
            'cache_path_prefix' => '.cache',
            'max_image_size' => 2000 * 2000,
        ]);

        $server->setCachePathCallable(function ($path, array $params) {
            $conversion = $params['conversion'] ?? 'default';

            if($conversion === 'default') {
                return null;
            }

            $filename = pathinfo($path, PATHINFO_FILENAME);
            $ext = strtolower($params['fm'] ?? config('shazzoo_media.default_extension'));

            // Normalize extension 
            if ($ext === 'pjpg') {
                $ext = 'jpg';
            }

            return "public/conversions/{$filename}/{$filename}-{$conversion}.{$ext}";
        });

        return $server;
    }
}
