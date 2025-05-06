# Shazzoo Media

[![License](https://img.shields.io/github/license/finnwiel/shazzoo-media.svg)](LICENSE)

A Laravel + Filament plugin that extends [Filament Curator](https://github.com/awcodes/filament-curator) with custom media conversion logic and a customized media model.

---

## 🚀 Features

- 🖼 Custom media model (`MediaExtended`) with JSON-stored conversions
- 🧩 Fully compatible with Filament Curator
- 🎨 Custom `CustomCuratorPicker` component with added conversion logic
- 📁 View overrides for Curator panel customization

---

## 📦 Installation
You can install the package via composer then run the installation command:

```bash
composer require finnwiel/shazzoo-media`
```
```bash
php artisan shazzoo_media:install
```

> **Note:** This package will install curator for you but you will have to do some of the setup. Like installing CropperJS and using a custom filament theme for styling. If you have not set up a custom theme and are using a Panel follow the instructions in the Filament Docs first.

```bash
npm install -D cropperjs
```


Import the plugin's stylesheet and cropperjs' stylesheet into your theme's css file.
```bash
@import '<path-to-vendor>/awcodes/filament-curator/resources/css/plugin.css';
```
Add the plugin's views to your ```tailwind.config.js``` file.
```bash
content: [
        './vendor/awcodes/filament-curator/resources/**/*.blade.php',
        './vendor/finnwiel/shazzoo-media/resources/views/vendor/curator/components/**/*.blade.php',
]
```

## 📦 Usage
### Global settings
The plugins settings can be managed through the config file

```bash
php artisan vendor:publish --tag=shazzoo_media-config
```

> **Note:** This package will also change some of curators settings, you can still manage curators setting but they may not work with Shazzoo Media

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
                    ->registerNavigation(true)
                    ->navigationCountBadge(true)   
            ])
}
```


### Picker field

Shazzoo Media adds a conversions method to the picker field, this method accepts an array. The conversions will subsequently be set in the database for each array item. Shazzoo Media will also create the conversion image.

```php
CustomCuratorPicker::make('featured_image_id')
                    ->conversions(['thumbnail']),
```

### Conversions

Conversions are set in ```config/shazzoo-media.php``` in the conversions array. To add or remove conversions change the array with the same sturcture.

```php
'conversions' => [
    'profile' => ['width' => 80,'height' => 80],
    'thumbnail' => ['width' => 200,'height' => 200],
    'medium' => ['width' => 400,'height' => 400],
    'large' => ['width' => 600,'height' => 600],
],
```





