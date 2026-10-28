// "12 Kingsway, London WC2B 6XF, United Kingdom" from a venue's (or an event's) address parts.
export function fullAddress(place = {}) {
    return [place.address, [place.city, place.zip_code].filter(Boolean).join(' '), place.country]
        .filter(Boolean)
        .join(', ');
}

export function mapUrl(place = {}) {
    return /^https?:\/\//i.test(place.map_url || '') ? place.map_url : '';
}
