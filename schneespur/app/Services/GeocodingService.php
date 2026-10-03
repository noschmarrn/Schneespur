<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    /**
     * @param  string|null  $state  Bundesstaat, Provinz oder Kanton. Am Ende
     *   und optional, damit ein Modul, das diesen Dienst heute mit drei
     *   Argumenten ruft, ohne Änderung weiterläuft.
     */
    public function resolve(string $street, string $zip, string $city, ?string $state = null): ?array
    {
        try {
            $query = [
                'street' => $street,
                'city' => $city,
                'postalcode' => $zip,
                'format' => 'json',
                'limit' => 1,
            ];

            // Ein leerer state-Parameter verschlechtert die Trefferqualität,
            // statt sie zu verbessern — also gar nicht erst mitschicken.
            if (is_string($state) && trim($state) !== '') {
                $query['state'] = trim($state);
            }

            $response = Http::withHeaders([
                'User-Agent' => brand_slug().'/1.0 ('.config('app.url').')',
            ])
                ->timeout(5)
                // No country filter: Wintertrace runs in the US, Canada, Norway
                // and beyond, and even a German operator may keep a site across
                // the border. Street, postcode and city are enough for Nominatim.
                ->get('https://nominatim.openstreetmap.org/search', $query);

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
