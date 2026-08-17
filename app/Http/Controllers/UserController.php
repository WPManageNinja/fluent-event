<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\Framework\Http\Request\Request;

class UserController extends Controller
{
    public function getUsers(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field');
        $roleIn = $request->getSafe('role_in', 'sanitize_text_field');

        $args = [
            'search'         => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number'         => 50,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
        ];

        if ($roleIn) {
            $args['role__in'] = array_filter(array_map(
                'sanitize_key',
                explode(',', $roleIn)
            ));
        }

        $users = get_users($args);

        return $this->sendSuccess([
            'users' => array_map(function ($user) {
                return [
                    'id'     => $user->ID,
                    'name'   => $user->display_name,
                    'email'  => $user->user_email,
                    'avatar' => get_avatar_url($user->ID),
                ];
            }, $users)
        ]);
    }
}
