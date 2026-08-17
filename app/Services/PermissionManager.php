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

            'read_statistics'  => __('Read access to Statistics and Reports', 'fluent-event'),

            'read_settings'    => __('Read access to Settings', 'fluent-event'),
            'write_settings'   => __('Write access to Settings', 'fluent-event'),

            self::ALL_DATA     => __('Manage all data and settings (full access)', 'fluent-event'),
        ];

        return apply_filters('fluent_event/permission/all_sets', $sets, []);
    }

    public static function getUserPermissions($userId = null): array
    {
        $userId = $userId ?: get_current_user_id();

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

    public static function userCan($permissions, array $options = []): bool
    {
        $granted = static::getUserPermissions();

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
