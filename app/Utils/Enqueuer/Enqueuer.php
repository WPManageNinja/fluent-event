<?php

namespace FluentEvent\App\Utils\Enqueuer;

use FluentEvent\App\App;

abstract class Enqueuer
{
    /**
     * Source resource directory relative to the plugin root. Override in a
     * subclass if your project keeps raw assets somewhere other than
     * `resources/`.
     *
     * @var string
     */
    protected static string $resourceDirectory = 'resources/';

    /**
     * The source resource directory (raw, pre-build). Used by dev-mode
     * enqueuers (e.g. Vite HMR) that serve directly from source.
     *
     * @return string
     */
    public static function getResourceDirectory(): string
    {
        return static::$resourceDirectory;
    }

    /**
     * URL base for built assets — resolved from the container's
     * `url.assets` binding (typically `plugin_dir_url() . 'assets/'`).
     *
     * @return string
     */
    public static function getAssetPath(): string
    {
        return App::getInstance()['url.assets'];
    }

    /**
     * Resolve a manifest entry to its full production asset URL.
     *
     * @param  array<string, mixed>  $file  A manifest row — expects a `file`
     *                                      key naming the built artifact.
     * @return string
     */
    public static function getProductionFilePath(array $file): string
    {
        if (empty($file['file'])) {
            return '';
        }

        return static::getAssetPath() . $file['file'];
    }

    /**
     * Resolve a path to its enqueue URL — asset base + relative path.
     *
     * @param  string  $path
     * @return string
     */
    public static function getEnqueuePath(string $path = ''): string
    {
        return static::getAssetPath() . $path;
    }
}
