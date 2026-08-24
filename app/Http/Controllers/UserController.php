<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\Framework\Http\Request\Request;

class UserController extends Controller
{
    public function getUsers(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field');

        $users = get_users([
            'search'         => $search ? '*' . $search . '*' : '',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number'         => 50,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
        ]);

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
