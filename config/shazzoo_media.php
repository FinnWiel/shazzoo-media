<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'max_image_height' => 1000, 
    'max_image_width' => 1000, 
    'check_duplicates' => false,

    /*
    |--------------------------------------------------------------------------
    | Access Control
    |--------------------------------------------------------------------------
    */
    'media_policies' => false, 
    'model' => \FinnWiel\ShazzooMedia\Models\ShazzooMedia::class, // Model to use for media

    /*
    |--------------------------------------------------------------------------
    | Conversion Settings
    |--------------------------------------------------------------------------
    */
    'conversion_ext' => 'webp', // Options: 'webp', 'jpg', 'png'
    'conversions' => [
        'profile' => ['width' => 80,'height' => 80, 'fit' => 'crop'],
        'thumbnail' => ['width' => 200,'height' => 200],
        'medium' => ['width' => 400,'height' => 400],
        'large' => ['width' => 600,'height' => 600,],
    ],
    'fit' => 'max', // Options: 'crop', 'max', 'contain', 'stretch', 

];
