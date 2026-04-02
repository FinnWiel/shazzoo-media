<?php

use FinnWiel\ShazzooMedia\Models\ShazzooMedia;

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'max_image_height' => 1000,
    'max_image_width' => 1000,
    'check_duplicates' => false,
    'min_size' => 0,
    'max_size' => 51200, // Max file size in KB (50MB)
    'directory' => 'media',
    'accepted_file_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
        'text/csv',
        'application/json',
        'video/mp4',
        'video/quicktime',
        'audio/mpeg',
        'audio/wav',
        'application/x-shockwave-flash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */
    'media_policies' => false,

    /*
    |--------------------------------------------------------------------------
    | Customize Plugin
    |--------------------------------------------------------------------------
    */
    'model' => ShazzooMedia::class, // Model to use for media
    'blocked_models_for_picker' => [], // Models that should not be available in the media picker
    'pagination' => 25,

    /*
    |--------------------------------------------------------------------------
    | Conversion Settings
    |--------------------------------------------------------------------------
    */
    'conversion_ext' => 'webp', // Options: 'webp', 'jpg', 'png'
    'conversions' => [
        'profile' => ['width' => 80, 'height' => 80, 'fit' => 'crop'],
        'thumbnail' => ['width' => 200, 'height' => 200],
        'medium' => ['width' => 400, 'height' => 400],
        'large' => ['width' => 600, 'height' => 600],
    ],
    'fit' => 'max', // Options: 'crop', 'max', 'contain', 'stretch',

];
