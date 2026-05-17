<?php

namespace App\Exceptions;

use RuntimeException;

class JobLifecycleException extends RuntimeException
{
    public static function shiftAlreadyActive(): self
    {
        return new self('Eine Schicht ist bereits aktiv. Beende die aktuelle Schicht, bevor du eine neue startest.');
    }

    public static function noActiveShift(): self
    {
        return new self('Keine aktive Schicht vorhanden. Starte zuerst eine Schicht.');
    }

    public static function jobAlreadyActive(): self
    {
        return new self('Ein Einsatz ist bereits aktiv. Beende den aktuellen Einsatz, bevor du einen neuen startest.');
    }

    public static function noActiveJob(): self
    {
        return new self('Kein aktiver Einsatz vorhanden.');
    }

    public static function activeJobMustEndFirst(): self
    {
        return new self('Ein aktiver Einsatz muss zuerst beendet werden, bevor die Schicht beendet werden kann.');
    }
}
