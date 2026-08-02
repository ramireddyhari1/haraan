{{-- Event form → shared places picker. Autocomplete fills the event's venue name,
     area/address, map link and the lat/lng pin. City is intentionally NOT mapped:
     it's a constrained Select (curated cities.json list that drives filtering), so
     the host picks it — an arbitrary Google locality wouldn't match an option. --}}
@include('filament.places-picker', [
    'fields' => [
        'lat'     => 'latitude',
        'lng'     => 'longitude',
        'name'    => 'venue',
        'address' => 'location',
        'mapLink' => 'map_link',
        'placeId' => 'place_id',
    ],
    'height' => 300,
])
