<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

return [

    /*
    |--------------------------------------------------------------------------
    | Manifest — Source Directory Mapping
    |--------------------------------------------------------------------------
    |
    | Maps a context name to the directory that contains its JS/Vue source files.
    | The i18n scanner uses this to find translatable strings in your frontend code.
    |
    | Format: 'context' => 'path/relative/to/plugin/root'
    |
    | Each context gets its own translation file (e.g. admin.js) and its own
    | set of translation strings in the 'translations' section below.
    |
    | Examples:
    |   'admin'   => 'resources/admin'      — admin panel SPA
    |   'public'  => 'resources/public'     — public-facing frontend
    |   'editor'  => 'resources/editor'     — Gutenberg block editor
    |
    */

    'manifest' => [
        'admin' => 'resources/admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translations — Localized Strings for Frontend
    |--------------------------------------------------------------------------
    |
    | Keyed by context (must match a manifest entry above).
    | Each value uses __() so `wp i18n make-pot` can extract them.
    | At runtime, these are already translated when the config is loaded
    | and passed to the frontend as a JSON object.
    |
    | Usage in Vue templates: {{ $t('key') }}
    | Usage in Vue scripts:   this.$t('key')
    |
    */

    'translations' => [
        'admin' => [
            'dashboard'  => __('Dashboard', 'fluent-events'),
            'settings'   => __('Settings', 'fluent-events'),
            'save'       => __('Save', 'fluent-events'),
            'cancel'     => __('Cancel', 'fluent-events'),
            'delete'     => __('Delete', 'fluent-events'),
            'edit'       => __('Edit', 'fluent-events'),
            'create'     => __('Create', 'fluent-events'),
            'search'     => __('Search', 'fluent-events'),
            'loading'    => __('Loading...', 'fluent-events'),
            'confirm'    => __('Confirm', 'fluent-events'),
            'yes'        => __('Yes', 'fluent-events'),
            'no'         => __('No', 'fluent-events'),
            'actions'    => __('Actions', 'fluent-events'),
            'status'     => __('Status', 'fluent-events'),
            'name'       => __('Name', 'fluent-events'),
            'error'      => __('Error', 'fluent-events'),
            'success'    => __('Success', 'fluent-events'),
            'no_results' => __('No results found', 'fluent-events'),
        ],
    ],
];
