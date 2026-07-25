{{-- Venue form → shared places picker. Autocomplete fills the venue name, full
     address, city, map link and the lat/lng pin. The "area/locality" short label
     stays manual (it's an editorial card label, not a Google field). --}}
@include('filament.places-picker', [
    'fields' => [
        'lat'     => 'latitude',
        'lng'     => 'longitude',
        'name'    => 'name',
        'address' => 'address',
        'mapLink' => 'map_link',
    ],
    'height' => 320,
])
