<?php

return [
	'site_url' => (function () {
		if ($url = getenv('WP_URL')) {
			return $url;
		}

		$pluginRoot = dirname(__DIR__, 2);
		$wpRoot = dirname($pluginRoot, 3);
		$wpConfig = $wpRoot . '/wp-config.php';

		if (file_exists($wpConfig)) {
			$content = file_get_contents($wpConfig);
			if (preg_match("/define\s*\(\s*['\"]WP_HOME['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
				return $m[1];
			}
			if (preg_match("/define\s*\(\s*['\"]WP_SITEURL['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $content, $m)) {
				return $m[1];
			}
		}

		return 'http://' . basename($wpRoot) . '.test';
	})(),

	'admin_user' => getenv('WP_USER') ?: 'admin',
	'admin_pass' => getenv('WP_PASS') ?: 'admin',
];
