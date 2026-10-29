<?php

namespace FluentEvent\App\Http\Requests;

use FluentEvent\App\CPT\VenueTaxonomy;
use FluentEvent\Framework\Validator\ValidationException;

class VenueRequest extends BaseRequest
{
    const PER_PAGE = 100;
    const SANITIZERS = [
        'name'     => 'sanitize_text_field',
        'address'  => 'sanitize_textarea_field',
        'city'     => 'sanitize_text_field',
        'zip_code' => 'sanitize_text_field',
        'country'  => 'sanitize_text_field',
        'map_url'  => 'sanitize_text_field',
    ];
    const RULES = [
        'name'     => 'required|string|max:255',
        'address'  => 'required|string|max:500',
        'city'     => 'nullable|string|max:100',
        'zip_code' => 'nullable|string|max:20',
        'country'  => 'nullable|string|max:100',
        'map_url'  => 'nullable|url',
    ];

    public static function messages()
    {
        return [
            'name.required'    => __('Venue name is required.', 'fluent-event'),
            'name.string'      => __('Venue name must be text.', 'fluent-event'),
            'name.max'         => __('Venue name cannot exceed 255 characters.', 'fluent-event'),
            'address.required' => __('Address is required.', 'fluent-event'),
            'address.string'   => __('Address must be text.', 'fluent-event'),
            'address.max'      => __('Address cannot exceed 500 characters.', 'fluent-event'),
            'city.string'      => __('City must be text.', 'fluent-event'),
            'city.max'         => __('City cannot exceed 100 characters.', 'fluent-event'),
            'zip_code.string'  => __('ZIP code must be text.', 'fluent-event'),
            'zip_code.max'     => __('ZIP code cannot exceed 20 characters.', 'fluent-event'),
            'country.string'   => __('Country must be text.', 'fluent-event'),
            'country.max'      => __('Country cannot exceed 100 characters.', 'fluent-event'),
            'map_url.url'      => __('Please provide a valid URL.', 'fluent-event'),
        ];
    }

    // `url` lets a dotless host like http://testinglink through, and the validator has no message hook for `regex`.
    public static function validateMapUrl($url)
    {
        if ($url && !preg_match('/^https?:\/\/([a-z0-9-]+\.)+[a-z]{2,}(:\d+)?([\/?#]\S*)?$/i', $url)) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'map_url' => [
                    'url' => esc_html__('Please provide a valid URL.', 'fluent-event')
                ]
            ]);
        }
    }

    // A term name must be unique in its taxonomy, and wp_update_term doesn't check it.
    public static function validateUniqueName($name, $venueId = 0)
    {
        $existing = get_term_by('name', $name, VenueTaxonomy::TAXONOMY);

        if ($existing && (int) $existing->term_id !== (int) $venueId) {
            throw new ValidationException('Unprocessable Entity!', 422, null, [
                'name' => [
                    'unique' => esc_html__('A venue with this name already exists.', 'fluent-event')
                ]
            ]);
        }
    }
}
