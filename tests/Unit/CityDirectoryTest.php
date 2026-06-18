<?php

use App\Support\CityDirectory;

it('snaps a coordinate to its nearest city anchor', function () {
    $resolved = CityDirectory::nearest(40.71, -74.00);

    expect($resolved['city'])->toBe('New York')
        ->and($resolved['country'])->toBe('United States')
        ->and($resolved['label'])->toBe('New York, United States')
        ->and($resolved['timezone'])->toBe('America/New_York');
});

it('resolves southern-hemisphere coordinates correctly', function () {
    $resolved = CityDirectory::nearest(-33.85, 151.2);

    expect($resolved['city'])->toBe('Sydney')
        ->and($resolved['timezone'])->toBe('Australia/Sydney');
});

it('exposes a sorted city list with bounding boxes for filtering', function () {
    $cities = CityDirectory::all();

    expect($cities)->not->toBeEmpty()
        ->and($cities[0])->toHaveKeys(['label', 'lat', 'lng', 'timezone', 'bounds']);
});

it('returns a bounding box for a known city label', function () {
    $bounds = CityDirectory::boundsForLabel('New York, United States');

    expect($bounds)->toHaveKeys(['south', 'west', 'north', 'east'])
        ->and($bounds['north'])->toBeGreaterThan($bounds['south']);

    expect(CityDirectory::boundsForLabel('Nowhere, Nowhere'))->toBeNull();
});
