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

##  Installation
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
Add the plugin's views to your ```tailwind.config.js``` file.
```php
content: [
        './vendor/awcodes/filament-curator/resources/**/*.blade.php',
        './vendor/finnwiel/shazzoo-media/resources/views/vendor/curator/components/**/*.blade.php',
]
```

##  Usage
### Global settings

The plugins settings can be managed through the config file

```bash
php artisan vendor:publish --tag=shazzoo_media-config
```

> **Note:** This plugin will also change some of curators settings, you can still manage curators setting but they may not work with Shazzoo Media
___
### Filament Panels
If you are using Filament Panels you will need to add the Plugin to your Panel's configuration. This will register the plugin's resources with the Panel. All methods are optional, and will be read from the config file if not provided.

```php
public function panel(Panel $panel): Panel
{
    return $panel
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
___

### Picker field

Shazzoo Media adds a conversions method to the picker field, this method accepts an array. The conversions will subsequently be set in the database for each array item. Shazzoo Media will also create the conversion image.

```php
ShazzooMediaCuratorPicker::make('featured_image_id')
                    ->conversions(['thumbnail']),
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
___
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

As you can see the existing conversions can also be edited, this can even be done when some conversions have already been made. Just be sure to run the ``` php artisan media:conversions:regenerate ``` command. This will regenerate the conversions to the new sizes.
___
### Artisan commands

The Shazzoo Media plugin uses some artisan commands. 
| Commands | Tags  | Uses |
|--------------|----|-----------------------------------------------------------------------------------------|
| `media:clear` | - | Clears your image files from the storage folder.|
| `media:conversions:clear-db` | `id` | Clears conversion(s) in the database. |
| `media:conversions:set-db` | `id` `append` | Sets conversion(s) in the databse. |
| `media:conversions:generate` | `id` `all` `only`| Generates the conversions for the images. |
| `media:conversions:regenerate` | `id` `only`| Regenerates the conversions for the images. |
| `media:conversions:list` | - | Lists out all image conversions |

___
### Policies & Tenancy

#### Policies

The Shazzoo Media library doesn't use a policy by default but lets you publish a policy template.

```php
php artisan vendor:publish --tag=shazzoo-media-policy
```

This will publish a policy file to `App/Policies/MediaPolicy.php` the policy should be registered automatically by the plugin. The published file will be a blank policy, you will need to add your own rules. Also make sure that `media_policies` is set to true in the `config/shazzoo_media.php`.

#### Tenancy

This package does not implement tenancy or user-based access control out of the box.

If your application requires scoping media by tenant, team, or user, you are responsible for applying your own global scope to the `MediaExtended` model. This can be done by publishing the model and implementing it there, or you can add the global scope in the `AppServiceProvider.php`'s boot function. 






