<?php

namespace FluentEvent\App\Utils\Enqueuer;

class Enqueue
{
    /**
     * Active enqueuer backend. Swap to Webpack::class (or a custom class that
     * implements the same enqueueStyle/enqueueScript/... static API) if the
     * plugin isn't using Vite.
     *
     * @var class-string
     */
    protected static $enqueuer = Vite::class;

    /**
     * Enqueue a stylesheet through the current build-tool backend (Vite).
     * Paths are resolved via the manifest; pass the logical src like
     * `admin/app.css` rather than an absolute URL.
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @return void
     */
    public static function style($handle, $src, $dependency = [], $version = null)
    {
        static::$enqueuer::enqueueStyle(
            $handle, $src, $dependency, $version
        );
    }

    /**
     * Enqueue a static (non-manifest) stylesheet — direct file from the
     * plugin's assets directory with no build-tool involvement.
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @return void
     */
    public static function staticStyle(
        $handle,
        $src,
        $dependency = [],
        $version = null
    )
    {
        static::$enqueuer::enqueueStaticStyle(
            $handle, $src, $dependency, $version
        );
    }

    /**
     * Enqueue a script through the current build-tool backend (Vite).
     * Returns whatever the backend's enqueueScript returns (typically the
     * final handle WordPress registered).
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @param  bool         $inFooter
     * @return mixed
     */
    public static function script(
        $handle,
        $src,
        $dependency = [],
        $version = null,
        $inFooter = false
    )
    {
        return static::$enqueuer::enqueueScript(
            $handle, $src, $dependency, $version, $inFooter
        );
    }

    /**
     * Enqueue a static (non-manifest) script — direct file from the plugin's
     * assets directory with no build-tool involvement.
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @param  bool         $inFooter
     * @return mixed
     */
    public static function staticScript(
        $handle,
        $src,
        $dependency = [],
        $version = null,
        $inFooter = false
    )
    {
        return static::$enqueuer::enqueueStaticScript(
            $handle, $src, $dependency, $version, $inFooter
        );
    }

    /**
     * Resolve a built source file to its URL, for callers that print their
     * own tags. Empty instead of throwing when the build is missing, so it
     * can't take down a page we don't own.
     *
     * @param  string  $src  Path relative to the resource root.
     * @return string
     */
    public static function url($src)
    {
        try {
            return static::$enqueuer::getUrl($src);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Whether the site loads the mirrored (.rtl.css) stylesheets.
     *
     * @return bool
     */
    public static function isRtl()
    {
        return static::$enqueuer::isRtl();
    }

    /**
     * Resolve a static asset to an absolute URL (plugin_dir_url + path)
     * without touching the build manifest. Used for images, fonts, or any
     * asset that isn't part of the Vite build graph.
     *
     * @param  string  $path  Path relative to the plugin's assets root.
     * @return string
     */
    public static function getStaticFilePath($path)
    {
        return static::$enqueuer::getStaticFilePath($path);
    }
}
