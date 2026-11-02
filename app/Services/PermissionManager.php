<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Meta;

class PermissionManager
{
    const PERMISSION_META_KEY = '_access_permissions';
    const ALL_DATA = 'manage_all_data';

    /**
     * Per-request cache of resolved permissions, keyed by user id.
     */
    protected static $cache = [];

    public static function allPermissionSets(): array
    {
        $sets = [
            'read_calendars'   => __('Read access to Calendars', 'fluent-event'),
            'write_calendars'  => __('Write access to Calendars', 'fluent-event'),

            'read_events'      => __('Read access to Events', 'fluent-event'),
            'write_events'     => __('Write access to Events', 'fluent-event'),

            'read_guests'      => __('Read access to Guests', 'fluent-event'),
            'write_guests'     => __('Write access to Guests', 'fluent-event'),

            'read_settings'    => __('Read access to Settings', 'fluent-event'),
            'write_settings'   => __('Write access to Settings', 'fluent-event'),

            self::ALL_DATA     => __('Manage all data and settings (full access)', 'fluent-event'),
        ];

        return apply_filters('fluent_event/permission/all_sets', $sets, []);
    }

    /**
     * `null` means the current user. An explicit `0` means NO user, and
     * resolves to no permissions — a caller acting for somebody else must not
     * silently inherit whoever happens to be logged in.
     *
     * @param int|null $userId
     */
    public static function getUserPermissions($userId = null): array
    {
        $userId = $userId ?? get_current_user_id();

        if (!$userId) return [];
        if (isset(static::$cache[$userId])) return static::$cache[$userId];

        if (user_can($userId, 'manage_options')) 
            return static::$cache[$userId] = [self::ALL_DATA];

        $meta = Meta::query()->ofUser($userId)->byKey(self::PERMISSION_META_KEY)->first();

        $granted = $meta ? array_values(array_intersect(
            array_keys(static::allPermissionSets()),
            (array) $meta->value
        )) : [];

        return static::$cache[$userId] = $granted;
    }

    /**
     * @param string|string[] $permissions
     * @param int|null        $userId  null = the current user
     */
    public static function userCan($permissions, $userId = null, array $options = []): bool
    {
        $granted = static::getUserPermissions($userId);

        if (in_array(self::ALL_DATA, $granted, true)) return true;

        $permissions = (array) $permissions;

        if (($options['match_type'] ?? 'any') === 'all') {
            // to match all the permissions
            return !array_diff($permissions, $granted);
        }

        // to match any of the permissions
        return (bool) array_intersect($permissions, $granted);
    }

    public static function hasAnyPermission(): bool
    {
        return (bool) static::getUserPermissions();
    }

    public static function getMenuCapabilities(): array
    {
        $access = [
            'dashboard' => static::hasAnyPermission(),
            'calendars' => static::userCan(['read_calendars', 'write_calendars']),
            'events'    => static::userCan(['read_events', 'write_events']),
            'add_event' => static::userCan('write_events'),
            'settings'  => static::userCan(['read_settings', 'write_settings']),
        ];

        if (current_user_can('manage_options')) {
            return array_fill_keys(array_keys($access), 'manage_options');
        }

        $roles = array_values((array) wp_get_current_user()->roles);
        $role = $roles[0] ?? '';

        $capabilities = [];

        foreach ($access as $item => $allowed) {
            $capabilities[$item] = $allowed ? $role : '';
        }

        return $capabilities;
    }

    public static function attachPermissions($userId, array $permissions): array
    {
        $valid = array_values(array_intersect(
            array_keys(static::allPermissionSets()),
            $permissions
        ));

        if (in_array(self::ALL_DATA, $valid, true)) $valid = [self::ALL_DATA];

        Meta::query()->updateOrCreate(
            [
                'object_type' => Meta::OBJECT_TYPE_USER,
                'object_id'   => $userId,
                'meta_key'    => self::PERMISSION_META_KEY,
            ],
            ['value' => $valid]
        );

        unset(static::$cache[$userId]);

        return $valid;
    }

    public static function detachPermissions($userId)
    {
        Meta::query()->ofUser($userId)->byKey(self::PERMISSION_META_KEY)->delete();

        unset(static::$cache[$userId]);
    }

    public static function getTeamMembers(): array
    {
        $rows = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_USER)
            ->byKey(self::PERMISSION_META_KEY)
            ->orderBy('id', 'ASC')
            ->get();

        $members = [];

        foreach ($rows as $row) {
            $user = get_user_by('ID', $row->object_id);
            if (!$user) continue;

            $members[] = [
                'id'          => $user->ID,
                'name'        => $user->display_name,
                'email'       => $user->user_email,
                'avatar'      => get_avatar_url($user->ID),
                'is_admin'    => user_can($user->ID, 'manage_options'),
                'permissions' => array_values(array_intersect(
                    array_keys(static::allPermissionSets()),
                    (array) $row->value
                )),
            ];
        }

        return $members;
    }
}
