<?php

namespace App\Enums;

enum WeatherMoment: string
{
    case Start = 'start';
    case End = 'end';

    public function label(): string
    {
        return __('weather.moment_' . $this->value);
    }
}
