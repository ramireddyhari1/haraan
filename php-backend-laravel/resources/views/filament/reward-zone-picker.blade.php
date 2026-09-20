{{-- Reward zone form → shared places picker. Search for the shop, turf or neighbourhood the
     zone is centred on; the pin fills the lat/lng the radius is measured from. The zone's own
     name stays manual — it is an internal label a marketer reads in a dropdown, not a business
     name, so "Kadapa city — 15 km" must survive picking a place called something else. --}}
@include('filament.places-picker', [
    'fields' => [
        'lat'     => 'latitude',
        'lng'     => 'longitude',
        'placeId' => 'place_id',
    ],
    'height' => 320,
])
