<?php

namespace FluentEvent\App\Utils\Enqueuer;

use Exception;
use FluentEvent\App\App;
use FluentEvent\Framework\Support\Arr;

class Vite extends Enqueuer
{
    /**
     * @method static enqueueScript($handle, $src, $dependency = [], $version = null, $inFooter = false)
     * 
     * @method static enqueueStyle($handle, $src, $dependency = [], $version = null)
     *
     * @method static getUrl($src)
     */

    /** @var array<string> */
    private array $moduleScripts = [];

    private bool $isScriptFilterAdded = false;

    protected static ?self $instance = null;

    protected static ?string $lastJsHandle = null;

    /** @var array<string, mixed>|null */
    private ?array $manifestData = null;

    /**
     * Read and cache the Vite `manifest.json` once per request. The manifest
     * maps logical source paths (e.g. `resources/admin/app.js`) to hashed
     * build artifacts so `enqueueScript()` / `enqueueStyle()` can pick up the
     * right filenames without the caller caring about content hashing.
     *
     * @return void
     */
    private function loadViteManifest(): void
    {
        if (!empty($this->manifestData)) {
            return;
        }

        $manifestPath = App::make('path.assets') . 'manifest.json';

        if (!file_exists($manifestPath)) {
            throw new Exception(
                'Vite Manifest Not Found. Run : npm run dev or npm run prod'
            );
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->manifestData = json_decode($wp_filesystem->get_contents($manifestPath), true);
    }

    /**
     * @param string $handle
     * @param string $src
     * @param array<string> $dependency
     * @param string|null $version
     * @param bool $inFooter
     * @return self
     */
    private function enqueueScript(
        string $handle,
        string $src,
        array $dependency = [],
        ?string $version = null,
        bool $inFooter = false
    )
    {
        $this->moduleScripts[] = $handle;
        static::$lastJsHandle = $handle;

        if (!$this->isScriptFilterAdded) {
            add_filter('script_loader_tag', function ($tag, $handle, $src) {
                return $this->addModuleToScript($tag, $handle, $src);
            }, 10, 3);

            $this->isScriptFilterAdded = true;
        }

        $srcPath = static::isOnDevMode()
            ? static::getVitePath() . $src
            : static::getProductionFilePath(
                $this->getFileFromManifest($src)
            );

        if (!static::isOnDevMode()) {
            static::enqueueDependentRecursiveStyle(
                $this->getFileFromManifest($src)
            );
        }

        wp_enqueue_script(
            $handle, $srcPath, $dependency, $version, $inFooter
        );

        return $this;
    }

    /**
     * @param string $handle
     * @param string $src
     * @param array<string> $dependency
     * @param string|null $version
     */
    private function enqueueStyle(string $handle, string $src, array $dependency = [], ?string $version = null): void
    {
        if (static::isOnDevMode()) {
            $srcPath = static::getVitePath() . ltrim($src, '/');
        } else {
            $manifestEntry = $this->getFileFromManifest($src);
            $srcPath = static::getProductionFilePath($manifestEntry);
        }

        wp_enqueue_style($handle, $srcPath, $dependency, $version);
    }

    /**
     * @param string $src
     * @return string
     */
    private function getUrl(string $src): string
    {
        if (static::isOnDevMode()) {
            return static::getVitePath() . ltrim($src, '/');
        }

        return static::getProductionFilePath($this->getFileFromManifest($src));
    }

    /**
     * @param string $src
     * @return array<string, mixed>
     */
    private function getFileFromManifest(string $src): array
    {
        $src = ltrim($src, '/');
        $resourceKey = static::getResourceDirectory() . $src;

        // 1. Try direct match (The key is usually the original source path)
        if (isset($this->manifestData[$resourceKey])) {
            return $this->manifestData[$resourceKey];
        }

        // 2. Reverse search (Check if any entry's 'file' property matches our request)
        // This removes the need for 'if ($src === admin/admin.css)'
        if (!empty($this->manifestData)) {
            foreach ($this->manifestData as $entry) {
                if (isset($entry['file']) && $entry['file'] === $src) {
                    return $entry;
                }
            }
        }

        // 3. Fallback for Dev Mode
        if (static::isOnDevMode()) {
            return [];
        }

        throw new Exception(esc_html("Source file '$src' (or key '$resourceKey') not found in Vite manifest."));
    }

    /**
     * @param array<string, mixed> $file
     */
    public static function enqueueDependentRecursiveStyle(array $file): void
    {
        $assetPath = static::getAssetPath();

        if (isset($file['css']) && is_array($file['css'])) {

            foreach ($file['css'] as $key => $path) {
                wp_enqueue_style(
                    $file['file'] . '_' . $key . '_css', $assetPath . $path, [], null
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function with(array $params): void
    {
        if (!Arr::isAssoc($params) || empty(static::$lastJsHandle)) {
            static::$lastJsHandle = null;
            return;
        }

        foreach ($params as $key => $val) {
            wp_localize_script(static::$lastJsHandle, $key, $val);
        }

        static::$lastJsHandle = null;
    }

    /**
     * Enqueue a script from the plugin's static assets folder — bypasses the
     * Vite manifest entirely. Use this for vendored third-party libraries
     * you ship as-is (no build-tool involvement).
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @param  bool         $inFooter
     * @return void
     */
    private function enqueueStaticScript(
        string $handle,
        string $src,
        array $dependency = [],
        ?string $version = null,
        bool $inFooter = false
    ): void
    {
        wp_enqueue_script(
            $handle,
            static::getEnqueuePath($src),
            $dependency,
            $version,
            $inFooter
        );
    }

    /**
     * Enqueue a stylesheet from the plugin's static assets folder — bypasses
     * the Vite manifest entirely. Use this for vendored third-party CSS.
     *
     * @param  string       $handle
     * @param  string       $src
     * @param  array        $dependency
     * @param  string|null  $version
     * @return void
     */
    private function enqueueStaticStyle(
        string $handle,
        string $src,
        array $dependency = [],
        ?string $version = null
    ): void
    {
        wp_enqueue_style(
            $handle, static::getEnqueuePath($src), $dependency, $version
        );
    }

    /**
     * True when the plugin is running in dev mode (config/app.php['env'] ===
     * 'dev'), which switches asset paths from the built `assets/` directory
     * to the Vite dev server URL.
     *
     * @return bool
     */
    public static function isOnDevMode(): bool
    {
        return App::getInstance()->config->get('app.env') === 'dev';
    }

    /**
     * Vite dev server URL base — reads the dynamic port from the plugin's
     * `.vite-port` file (written by `npm run watch`) or falls back to 8880.
     * Append the resource path to get the full URL of a source file served
     * by the HMR server.
     *
     * @return string
     */
    public static function getVitePath(): string
    {
        $portFile = App::make('path') . '.vite-port';
        $vitePort = '8880';

        if (file_exists($portFile)) {
            $fileContent = trim(file_get_contents($portFile));
            if (!empty($fileContent)) {
                $vitePort = $fileContent;
            }
        }

        return 'http://localhost:'
            . $vitePort . '/'
            . static::getResourceDirectory();
    }

    /**
     * Resolve a relative path to its serveable URL — Vite dev server in dev
     * mode, built `assets/` directory in production. Callers don't need to
     * know which they're in.
     *
     * @param  string  $path  Relative to the resource root.
     * @return string
     */
    public static function getEnqueuePath($path = ''): string
    {
        return (
            static::isOnDevMode()
                ? static::getVitePath()
                : static::getAssetPath()
            ) . $path;
    }

    /**
     * Alias for `getEnqueuePath()` kept as a distinct entry point so callers
     * communicating "I'm resolving a static asset" (image, font, JSON) read
     * self-explanatorily — the dev vs prod switch is the same either way.
     *
     * @param  string  $path
     * @return string
     */
    public static function getStaticFilePath($path = ''): string
    {
        return static::getEnqueuePath($path);
    }

    /**
     * Attach `type="module"` to script tags for handles Vite emits as ES
     * modules — Vite's build graph requires module-type script tags for any
     * handle that does dynamic import or uses ESM syntax, and WP's default
     * enqueue doesn't set that attribute.
     *
     * Hooked into the `script_loader_tag` filter.
     *
     * @param  string  $tag     The `<script>` tag HTML as WP built it.
     * @param  string  $handle  Script handle being rendered.
     * @param  string  $src     Final src URL.
     * @return string            Rewritten tag (with `type="module"`) or original.
     */
    private function addModuleToScript(string $tag, string $handle, string $src): string
    {
        if (in_array($handle, $this->moduleScripts)) {
            return wp_get_script_tag([
                'src' => esc_url($src),
                'type' => 'module'
            ]);
        }

        return $tag;
    }

    /**
     * @param string $method
     * @param array<mixed> $params
     * @return mixed
     */
    public static function __callStatic(string $method, array $params)
    {
        if (static::$instance === null) {
            
            static::$instance = new static();

            if (!static::isOnDevMode()) {
                static::$instance->loadViteManifest();
            }
        }

        return call_user_func_array([static::$instance, $method], $params);
    }
}
