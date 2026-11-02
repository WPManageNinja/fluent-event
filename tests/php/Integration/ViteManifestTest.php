<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\App;
use FluentEvent\App\Utils\Enqueuer\Vite;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * The manifest is our own local file. Reading it must not depend on how WordPress
 * would *write* files on this host, which is all the filesystem API decides.
 */
class ViteManifestTest extends IntegrationTestCase
{
    public function test_the_manifest_loads_when_the_filesystem_api_cannot_connect()
    {
        if (!file_exists(App::make('path.assets') . 'manifest.json')) {
            $this->markTestSkipped('No built manifest — run `npm run build` first.');
        }

        global $wp_filesystem;
        $previous = $wp_filesystem;
        $wp_filesystem = null;

        // FTP with no stored credentials: WP_Filesystem() cannot connect, as on many shared hosts.
        $this->addTestFilter('filesystem_method', function () {
            return 'ftpext';
        });

        try {
            $vite = (new \ReflectionClass(Vite::class))->newInstanceWithoutConstructor();
            $this->reflect(new \ReflectionMethod(Vite::class, 'loadViteManifest'))->invoke($vite);
            $manifest = $this->reflect(new \ReflectionProperty(Vite::class, 'manifestData'))->getValue($vite);
        } finally {
            $wp_filesystem = $previous;
        }

        $this->assertIsArray($manifest, 'The manifest did not load.');
        $this->assertArrayHasKey('resources/admin/app.js', $manifest);
    }

    private function reflect($reflector)
    {
        if (PHP_VERSION_ID < 80100) {
            $reflector->setAccessible(true);
        }

        return $reflector;
    }
}
