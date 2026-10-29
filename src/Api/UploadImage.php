<?php

namespace ErnestDefoe\Herald\Api;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Arr;
use Intervention\Image\ImageManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * An image for a builder block.
 *
 * 🚨 The bytes decide, never the name or the browser's type: the file is
 * sniffed (JPEG, PNG, GIF or WebP only — no SVG, which is a document that can
 * carry script), its pixel count is capped before it is decoded, and it is
 * re-encoded from the decoded pixels, which drops anything hidden in the
 * original. It is stored under a random name in Herald's own folder of the
 * forum's assets.
 */
class UploadImage extends Controller
{
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_PIXELS = 40_000_000;
    private const WIDTH = 1200;

    public function __construct(
        private ImageManager $images,
        private Factory $filesystem,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): mixed
    {
        $file = Arr::get($request->getUploadedFiles(), 'image');
        $invalid = fn () => new ValidationException(['image' => resolve('translator')->trans('ernestdefoe-herald.forum.errors.image_invalid')]);

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK || $file->getSize() > self::MAX_BYTES) {
            throw $invalid();
        }

        $bytes = (string) $file->getStream();
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw $invalid();
        }

        try {
            $image = $this->images->read($bytes);
            $image->scaleDown(width: self::WIDTH);

            // Photos stay JPEG; anything that may be transparent becomes PNG
            // (WebP is not shown by every mail client); animation is kept.
            [$encoded, $extension] = match (true) {
                $image->isAnimated() => [$image->toGif(), 'gif'],
                $info[2] === IMAGETYPE_JPEG => [$image->toJpeg(quality: 85), 'jpg'],
                default => [$image->toPng(), 'png'],
            };
        } catch (Throwable) {
            throw $invalid();
        }

        $path = 'herald/'.bin2hex(random_bytes(16)).'.'.$extension;
        $disk = $this->filesystem->disk('flarum-assets');
        $disk->put($path, (string) $encoded);

        return ['url' => $disk->url($path), 'width' => $image->width(), 'height' => $image->height()];
    }
}
