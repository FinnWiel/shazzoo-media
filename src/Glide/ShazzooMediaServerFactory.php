<?php

namespace FinnWiel\ShazzooMedia\Glide;

use Awcodes\Curator\Glide\Contracts\ServerFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Glide\Responses\SymfonyResponseFactory;
use League\Glide\Server;
use League\Glide\ServerFactory as GlideServerFactory;

class ShazzooMediaServerFactory implements ServerFactory
{
    public function getFactory(): GlideServerFactory | Server
    {
        $filesystem = new Filesystem(
            new LocalFilesystemAdapter(storage_path('app'))
        );

        $server = GlideServerFactory::create([
            'driver' => 'gd',
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => $filesystem,
            'source_path_prefix' => 'public',
            'cache' => $filesystem,
            'cache_path_prefix' => '.cache',
            'max_image_size' => 2000 * 2000,
        ]);

        $server->setCachePathCallable(function ($path, array $params) {
            $conversion = $params['conversion'] ?? 'default';

            if ($conversion === 'default') {
                return null;
            }

            $filename = pathinfo($path, PATHINFO_FILENAME);
            $ext = strtolower($params['fm'] ?? config('shazzoo_media.default_extension', 'webp'));

            // Normalize extension
            if ($ext === 'pjpg') {
                $ext = 'jpg';
            }

            // Try to extract the media ID from the path prefix: media/{id}/...
            if (preg_match('#^media/(\d+)/#', $path, $matches)) {
                $mediaId = $matches[1];
            } else {
                return null; // Fallback: don't generate a conversion
            }

            return "public/media/{$mediaId}/conversions/{$filename}-{$conversion}.{$ext}";
        });


        return $server;
    }
}
