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
            'dashboard'  => __('Dashboard', 'fluent-event'),
            'settings'   => __('Settings', 'fluent-event'),
            'save'       => __('Save', 'fluent-event'),
            'cancel'     => __('Cancel', 'fluent-event'),
            'delete'     => __('Delete', 'fluent-event'),
            'edit'       => __('Edit', 'fluent-event'),
            'create'     => __('Create', 'fluent-event'),
            'search'     => __('Search', 'fluent-event'),
            'loading'    => __('Loading...', 'fluent-event'),
            'confirm'    => __('Confirm', 'fluent-event'),
            'yes'        => __('Yes', 'fluent-event'),
            'no'         => __('No', 'fluent-event'),
            'actions'    => __('Actions', 'fluent-event'),
            'status'     => __('Status', 'fluent-event'),
            'name'       => __('Name', 'fluent-event'),
            'error'      => __('Error', 'fluent-event'),
            'success'    => __('Success', 'fluent-event'),
            'no_results' => __('No results found', 'fluent-event'),
        ],
    ],
];
