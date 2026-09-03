<?php

namespace App\Services\Weather;

use Carbon\CarbonInterface;

/**
 * Zusätzliche Fähigkeit, nicht Teil von WeatherProviderInterface.
 *
 * Als Pflichtmethode am Hauptinterface bräche sie jeden von einem Modul
 * registrierten Anbieter (die WeatherProviderRegistry ist eine
 * ExtensionRegistry). Anbieter ohne Archiv — MET Norway — implementieren sie
 * schlicht nicht; der Aufrufer prüft mit instanceof.
 */
interface ArchiveWeatherProviderInterface
{
    /**
     * Das Wetter zu einem vergangenen Zeitpunkt.
     *
     * $moment wird stundengenau ausgewertet und in UTC gelesen — so, wie die
     * Zeitstempel seit 1.2.1 in der Datenbank stehen. null, wenn der Anbieter
     * für diese Stunde nichts hat; die vorhandene Datenqualitätswarnung
     * „Wetter fehlt" greift dann.
     */
    public function fetchAt(float $lat, float $lon, CarbonInterface $moment): ?WeatherData;
}
