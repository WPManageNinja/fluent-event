<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Http\Requests\VenueRequest;
use FluentEvent\App\Services\VenueService;
use FluentEvent\Framework\Http\Request\Request;

class VenueController extends Controller
{
    public function getVenues(Request $request)
    {
        return [
            'venues' => VenueService::paginate(
                $request->getSafe('search', 'sanitize_text_field'),
                max(1, (int) $request->getSafe('page', 'intval', 1)),
                VenueRequest::perPage($request)
            ),
        ];
    }

    public function getVenueOptions(Request $request)
    {
        return ['venues' => VenueService::options()];
    }

    public function createVenue(Request $request)
    {
        $data = VenueRequest::sanitize($request->all());
        $this->validate($data, VenueRequest::RULES, VenueRequest::messages());
        VenueRequest::validateMapUrl($data['map_url'] ?? '');
        VenueRequest::validateUniqueName($data['name']);

        $venue = VenueService::create($data);

        do_action('fluent_event/venue/created', $venue);

        return [
            'message' => __('Venue has been successfully created', 'fluent-event'),
            'venue'   => VenueService::format($venue),
        ];
    }

    public function updateVenue(Request $request, $id)
    {
        $venue = VenueService::find($id);

        if (!$venue) {
            return $this->sendError(['message' => __('This venue no longer exists.', 'fluent-event')], 404);
        }

        $data = VenueRequest::sanitize($request->all());
        $this->validate($data, VenueRequest::RULES, VenueRequest::messages());
        VenueRequest::validateMapUrl($data['map_url'] ?? '');
        VenueRequest::validateUniqueName($data['name'], $venue->term_id);

        $venue = VenueService::update($venue, $data);
        VenueService::syncEvents($venue);

        do_action('fluent_event/venue/updated', $venue);

        return [
            'message' => __('Venue has been successfully updated', 'fluent-event'),
            'venue'   => VenueService::format($venue),
        ];
    }

    public function deleteVenue(Request $request, $id)
    {
        $venue = VenueService::find($id);

        if (!$venue) {
            return $this->sendError(['message' => __('This venue no longer exists.', 'fluent-event')], 404);
        }

        do_action('fluent_event/venue/before_deleted', $venue);

        VenueService::delete($venue);

        do_action('fluent_event/venue/deleted', $venue);

        return [
            'message' => __('Venue has been successfully deleted', 'fluent-event'),
        ];
    }
}
