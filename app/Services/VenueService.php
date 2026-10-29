<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\CPT\VenueTaxonomy;
use FluentEvent\App\Models\Event;
use FluentEvent\Framework\Support\Arr;
use FluentEvent\Framework\Foundation\Exceptions\HttpException;

/**
 * Venues are `fe_venue` terms: the name is the term name, the rest is term meta.
 * Reads and writes go through WordPress' term API, so the WP_Error-to-422 translation happens here once.
 */
class VenueService
{
    const FIELDS = ['address', 'city', 'zip_code', 'country', 'map_url'];

    /** @return \WP_Term|null */
    public static function find($id)
    {
        $term = get_term((int) $id, VenueTaxonomy::TAXONOMY);

        return $term instanceof \WP_Term ? $term : null;
    }

    public static function paginate($search, $page, $perPage)
    {
        $args = [
            'taxonomy'   => VenueTaxonomy::TAXONOMY,
            'hide_empty' => false,
            'search'     => $search,
        ];

        $terms = get_terms($args + [
            'orderby' => 'term_id',
            'order'   => 'DESC',
            'number'  => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        return [
            'data'         => array_map([static::class, 'format'], is_wp_error($terms) ? [] : $terms),
            'total'        => (int) wp_count_terms($args),
            'per_page'     => $perPage,
            'current_page' => $page,
        ];
    }

    // Every venue, A to Z, for the event form's picker.
    public static function options()
    {
        $terms = get_terms([
            'taxonomy'   => VenueTaxonomy::TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        return array_map([static::class, 'format'], is_wp_error($terms) ? [] : $terms);
    }

    public static function format(\WP_Term $term)
    {
        $venue = [
            'id'   => (int) $term->term_id,
            // WordPress stores term names HTML-escaped; every sink escapes it again.
            'name' => wp_specialchars_decode($term->name, ENT_QUOTES),
        ];

        foreach (self::FIELDS as $field) {
            $venue[$field] = (string) get_term_meta($term->term_id, $field, true);
        }

        return $venue;
    }

    /** @return \WP_Term */
    public static function create($data)
    {
        $result = wp_insert_term(wp_slash(Arr::get($data, 'name')), VenueTaxonomy::TAXONOMY);

        if (is_wp_error($result)) {
            throw new HttpException(422, esc_html__('The venue could not be saved. Please try again.', 'fluent-event'), 'venue_insert_failed');
        }

        self::saveFields($result['term_id'], $data);

        return self::find($result['term_id']);
    }

    /** @return \WP_Term */
    public static function update(\WP_Term $venue, $data)
    {
        $result = wp_update_term($venue->term_id, VenueTaxonomy::TAXONOMY, [
            'name' => wp_slash(Arr::get($data, 'name')),
        ]);

        if (is_wp_error($result)) {
            throw new HttpException(422, esc_html__('The venue could not be saved. Please try again.', 'fluent-event'), 'venue_update_failed');
        }

        self::saveFields($venue->term_id, $data);

        return self::find($venue->term_id);
    }

    public static function delete(\WP_Term $venue)
    {
        wp_delete_term($venue->term_id, VenueTaxonomy::TAXONOMY);
    }

    // The event keeps its own copy, so it still shows a venue that is later deleted.
    public static function eventFields(\WP_Term $venue)
    {
        $venue = self::format($venue);

        return ['venue' => $venue['name']] + Arr::only($venue, self::FIELDS);
    }

    public static function createFromEvent(array $data)
    {
        return self::create(['name' => Arr::get($data, 'venue')] + Arr::only($data, self::FIELDS));
    }

    public static function linkEvent($postId, $venueId)
    {
        wp_set_object_terms($postId, $venueId ? [(int) $venueId] : [], VenueTaxonomy::TAXONOMY);
    }

    public static function eventVenueId($postId)
    {
        $ids = wp_get_object_terms($postId, VenueTaxonomy::TAXONOMY, ['fields' => 'ids']);

        return is_wp_error($ids) || !$ids ? null : (int) $ids[0];
    }

    // Events follow their venue: an edit reaches every event linked to it.
    public static function syncEvents(\WP_Term $venue)
    {
        $postIds = get_objects_in_term($venue->term_id, VenueTaxonomy::TAXONOMY);

        if (is_wp_error($postIds) || !$postIds) {
            return;
        }

        $fields = self::eventFields($venue);

        foreach (Event::query()->whereIn('post_id', $postIds)->get() as $event) {
            $event->meta = array_merge((array) $event->meta, $fields);
            $event->save();
        }
    }

    protected static function saveFields($termId, $data)
    {
        foreach (self::FIELDS as $field) {
            update_term_meta($termId, $field, wp_slash((string) Arr::get($data, $field, '')));
        }
    }
}
