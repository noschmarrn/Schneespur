<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_the_request_carries_no_country_filter(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '44.9772995', 'lon' => '-93.2654692'],
            ]),
        ]);

        $result = app(GeocodingService::class)->resolve('2400 Nicollet Ave', '55404', 'Minneapolis');

        $this->assertSame(['lat' => 44.9772995, 'lon' => -93.2654692], $result);

        Http::assertSent(function ($request) {
            // The hardcoded 'de' is what made every non-German address fail.
            return ! array_key_exists('country', $request->data())
                && $request->data()['city'] === 'Minneapolis'
                && $request->data()['postalcode'] === '55404';
        });
    }

    public function test_an_empty_result_still_yields_null(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->assertNull(app(GeocodingService::class)->resolve('Nowhere', '00000', 'Nirgendwo'));
    }
}
