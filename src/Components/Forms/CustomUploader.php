<?php

namespace FinnWiel\ShazzooMedia\Components\Forms;

use Awcodes\Curator\Components\Forms\Uploader;
use Filament\Http\Livewire\Auth\Login;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CustomUploader extends Uploader
{
    protected bool $keepOriginalSize = false;

    public function keepOriginalSize(bool $state = true): static
    {
        $this->keepOriginalSize = $state;
        return $this;
    }

    public function shouldKeepOriginalSize(): bool
    {
        return $this->keepOriginalSize;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateUpdated(function ($state, $livewire, $component) {

            if ($this->shouldKeepOriginalSize()) {
                return;
            }

            if (empty($state)) {
                return;
            }

            $files = is_array($state) ? $state : [$state];

            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile && file_exists($file->getRealPath())) {
                    $this->resizeImage($file->getRealPath());
                }
            }
        });
    }

    protected function resizeImage(string $sourcePath): void
    {
        [$width, $height, $imageType] = getimagesize($sourcePath);

        if (!$width || !$height) {
            return;
        }

        $maxWidth = config('shazzoo_media.max_image_width', 1000);
        $maxHeight = config('shazzoo_media.max_image_height', 1000); 

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        // Image doesn't exceed the maximum dimensions
        if ($ratio >= 1) {
            return;
        }

        //Set new dimensions
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Create a new image from the source
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $srcImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return;
        }

        if (!$srcImage) {
            return;
        }

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and WEBP
        if (in_array($imageType, [IMAGETYPE_PNG,  IMAGETYPE_WEBP])) {
            imagecolortransparent($dstImage, imagecolorallocatealpha($dstImage, 0, 0, 0, 127));
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save the resized image over the original
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($dstImage, $sourcePath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($dstImage, $sourcePath, 6);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($dstImage, $sourcePath, 90);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);
    }
}
