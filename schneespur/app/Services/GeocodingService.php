<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function resolve(string $street, string $zip, string $city): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => brand_slug().'/1.0 ('.config('app.url').')',
            ])
                ->timeout(5)
                // No country filter: Wintertrace runs in the US, Canada, Norway
                // and beyond, and even a German operator may keep a site across
                // the border. Street, postcode and city are enough for Nominatim.
                ->get('https://nominatim.openstreetmap.org/search', [
                    'street' => $street,
                    'city' => $city,
                    'postalcode' => $zip,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (! empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
                    return [
                        'lat' => (float) $data[0]['lat'],
                        'lon' => (float) $data[0]['lon'],
                    ];
                }
            }

            return null;
        } catch (ConnectionException|RequestException $e) {
            return null;
        }
    }
}
