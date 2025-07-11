# Shazzoo Media

[![License](https://img.shields.io/github/license/finnwiel/shazzoo-media.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/finnwiel/shazzoo-media.svg)](https://packagist.org/packages/finnwiel/shazzoo-media)
![Laravel](https://img.shields.io/badge/laravel-12.x-red)
![Filament](https://img.shields.io/badge/filament-3.x-yellow)
![PHP](https://img.shields.io/badge/php-^8.1-blue)

A Laravel + Filament plugin that extends [Filament Curator](https://github.com/awcodes/filament-curator) with custom media conversion logic and a customized media model.

---

- [Installation](#-installation)
- [Usage](#-usage)
  - [Global Settings](#global-settings)
  - [Filament Panels](#filament-panels)
  - [Picker Field](#picker-field)
  - [Conversions](#conversions)

---

## Installation

⚠️ **Important:** Before installing, make sure the GD extension is enabled in your php.ini.
Without it, image conversions will fail and no thumbnails will be generated.

You can install the package via composer then run the installation command:

```bash
composer require finnwiel/shazzoo-media
```

If you installed Curator before Shazzoo Media, you’ll need to remove Curator’s media table migration manually before running the install command.

```bash
php artisan shazzoo_media:install
```

> **Note:** This plugin will install curator for you but you will have to do some of the setup. Like installing CropperJS and using a custom filament theme for styling. If you have not set up a custom theme and are using a Panel follow the instructions in the Filament Docs first.

```bash
npm install -D cropperjs
```

Import the plugin's stylesheet and cropperjs' stylesheet into your theme's css file.

```php
@import '<path-to-vendor>/awcodes/filament-curator/resources/css/plugin.css';
```

Add the plugin's views to your custom theme's `tailwind.config.js` file.

```php
content: [
        './vendor/awcodes/filament-curator/resources/**/*.blade.php',
        './vendor/finnwiel/shazzoo-media/resources/views/components/**/*.blade.php',
]
```

## Usage

### Global settings

The plugins settings can be managed through the config file

```bash
php artisan vendor:publish --tag=shazzoo_media-config
```

> **Note:** This plugin will also change some of curators settings, you can still manage curators setting but they may not work with Shazzoo Media


To publish the plugins Model run: 

```bash
php artisan vendor:publish --tag=shazzoo-media-model
```

**Important:** When publishing the model make sure to also change the model in the config.

---

### Filament Panels

If you are using Filament Panels you will need to add the Plugin to your Panel's configuration. This will register the plugin's resources with the Panel. All methods are optional, and will be read from the config file if not provided.

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->colors([
                'primary' => Color::Amber,
                'secondary' => Color::Cyan, // Add a secondary color
            ])
        ->plugins([
                \Awcodes\Curator\CuratorPlugin::make()
                    ->label('Media')
                    ->navigationLabel('Media Library')
                    ->resource(MediaResource::class) // use FinnWiel\ShazzooMedia\Resources\MediaResource;
                    ->registerNavigation(true)
                    ->navigationCountBadge(true)
            ])
}
```

---

### Picker field

Shazzoo Media adds a conversions method to the picker field, this method accepts an array. The conversions will subsequently be set in the database for each array item. Shazzoo Media will also create the conversion image.

The picker also has a fileType method, this accepts the following strings: 'image', 'icon', 'document'. 

| Commands | Tags | 
|--------------|----|
| `image` | `jpeg` `png`, `webp`, `gif` |
| `icon` | `svg` |
| `document` | `pdf` `docx` | 

```php
ShazzooMediaPicker::make('featured_image_id')
                    ->conversions(['thumbnail'])
                    ->fileType(), // 'image', 'icon', 'document'
```


To generate actually set the conversions in the database you need to add a trait to the create and edit classes of your resource.

```php
use FinnWiel\ShazzooMedia\Traits\HandlesConversions;

class CreatePost extends CreateRecord
{
    use HandlesConversions;

    protected static string $resource = PostResource::class;
}
```

---

### Conversions

Conversions are set in `config/shazzoo_media.php` in the conversions array. To add or remove conversions change the array with the same structure.

```php
'conversions' => [
    'profile' => ['width' => 80, 'height' => 80],
    'thumbnail' => ['width' => 200, 'height' => 200],
    'medium' => ['width' => 400, 'height' => 400],
    'large' => ['width' => 600, 'height' => 600],
],
```

So adding a new conversion called small would look like this:

```php
'conversions' => [
    'profile' => ['width' => 80, 'height' => 80, 'fit' => 'crop'],
    'thumbnail' => ['width' => 200, 'height' => 200],
    'medium' => ['width' => 400, 'height' => 400],
    'large' => ['width' => 600, 'height' => 600],
    'small' => ['width' => 100, 'height' => 100],
],
```

As you can see the existing conversions can also be edited, this can even be done when some conversions have already been made. Just be sure to run the `php artisan media:conversions:regenerate` command. This will regenerate the conversions to the new sizes.

---

### Showing images

To show images in your frontend using the `ShazzooMedia` model, you can leverage the built-in dynamic URL accessors for media conversions.

For each image conversion defined in your config (shazzoo_media.conversions), a dynamic property is available on your media model:

```php
$image->thumbnail_url  // returns URL for the 'thumbnail' conversion
$image->web_url        // returns URL for the 'web' conversion
```

If the conversion file exists, it returns the converted image URL. If not, it gracefully falls back to the original image URL.

Example in blade:

```blade
<img src="{{ $media->thumbnail_url }}" alt="Thumbnail">
```

#### Defining relationships

You are responsible for defining the relationship between your Eloquent models and the media records.

#####  One-to-One Example:
For a model that has a single media item, like a `Post` with a featured image:

```php
// In your Post.php model
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;

public function featuredImage()
{
    return $this->belongsTo(ShazzooMedia::class, 'media_id');
}
```

Usage:

```blade
<img src="{{ $post->featuredImage?->thumbnail_url }}" alt="Featured image">
```

#####  One-to-Many Example:
For a model that has multiple images, like a `Product` with a gallery:

```php
// In your Product.php model
use FinnWiel\ShazzooMedia\Models\ShazzooMedia;

public function gallery()
{
    return $this->hasMany(ShazzooMedia::class, 'product_id');
}
```

Usage:

```blade
@foreach($product->gallery as $image)
    <img src="{{ $image->web_url }}" alt="Gallery image">
@endforeach
```


---

### Artisan commands

The Shazzoo Media plugin uses some artisan commands.
| Commands | Tags | Uses |
|--------------|----|-----------------------------------------------------------------------------------------|
| `media:clear` | - | Clears your image files from the storage folder.|
| `media:clear-conversions` | - | Clears your image conversion files from the storage folder.|
| `media:conversions:clear-db` | `id` | Clears conversion(s) in the database. |
| `media:conversions:set-db` | `id` `append` | Sets conversion(s) in the databse. |
| `media:conversions:generate` | `id` `all` `only`| Generates the conversions for the images. |
| `media:conversions:regenerate` | `id` `only`| Regenerates the conversions for the images. |
| `media:conversions:list` | - | Lists out all image conversions |

---

### Policies & Tenancy

#### Policies

The Shazzoo Media library doesn't use a policy by default but lets you publish a policy template.

```php
php artisan vendor:publish --tag=shazzoo-media-policy
```

This will publish a policy file to `App/Policies/MediaPolicy.php` the policy should be registered automatically by the plugin. The published file will be a blank policy, you will need to add your own rules. Also make sure that `media_policies` is set to true in the `config/shazzoo_media.php`.

#### Tenancy

This package does not implement tenancy or user-based access control out of the box.

If your application requires scoping media by tenant, team, or user, you are responsible for applying your own global scope to the `ShazzooMedia` model. This can be done by publishing the model and implementing it there, or you can add the global scope in the `AppServiceProvider.php`'s boot function.
