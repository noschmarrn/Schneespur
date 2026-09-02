<?php

namespace Tests\Feature;

use App\Services\Weather\ConditionMapper;
use Tests\TestCase;

class WeatherCodeDisplayTest extends TestCase
{
    public function test_a_wmo_code_maps_to_a_translated_label(): void
    {
        app()->setLocale('de');

        $label = ConditionMapper::label(ConditionMapper::fromWmoCode(1));

        $this->assertNotSame('1', $label);
        $this->assertNotSame('weather.condition_clear', $label);
        $this->assertNotEmpty($label);
    }

    public function test_a_missing_code_does_not_produce_an_empty_cell(): void
    {
        $this->assertNotEmpty(ConditionMapper::label(ConditionMapper::fromWmoCode(null)));
    }
}
