<?php

namespace FluentEvent\App\CPT;

/**
 * The `fe_venue` taxonomy on `fe_event`: a venue saved once and picked for any event.
 *
 * The name is the term name; the address and the optional city, ZIP code, country and
 * map link are term meta (VenueService::FIELDS). Like the post type it has no WordPress
 * UI, front-end route or core REST endpoint; the SPA manages venues through our API.
 */
class VenueTaxonomy
{
    const TAXONOMY = 'fe_venue';

    /**
     * Register the taxonomy. Called from CPTHandler on `init`.
     *
     * @return void
     */
    public function registerTaxonomy()
    {
        register_taxonomy(static::TAXONOMY, EventPost::POST_TYPE, [
            'labels'                => $this->getLabels(),
            'public'                => false,
            'publicly_queryable'    => false,
            'rewrite'               => false,
            'query_var'             => false,
            'show_ui'               => false,
            'show_in_menu'          => false,
            'show_in_nav_menus'     => false,
            'show_tagcloud'         => false,
            'show_in_quick_edit'    => false,
            'show_admin_column'     => false,
            'show_in_rest'          => false,
            'hierarchical'          => false,
            // Counts every linked event; the default counts published posts only, and events can be private or drafts.
            'update_count_callback' => '_update_generic_term_count',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getLabels()
    {
        return [
            'name'          => _x('Venues', 'taxonomy general name', 'fluent-event'),
            'singular_name' => _x('Venue', 'taxonomy singular name', 'fluent-event'),
            'add_new_item'  => __('Add New Venue', 'fluent-event'),
            'edit_item'     => __('Edit Venue', 'fluent-event'),
            'search_items'  => __('Search Venues', 'fluent-event'),
            'not_found'     => __('No venues found', 'fluent-event'),
        ];
    }
}
