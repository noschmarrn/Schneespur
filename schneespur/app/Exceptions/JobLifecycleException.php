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

    /**
     * A lifecycle field handler marked `critical` failed. The original cause
     * is preserved as $previous — it is already logged (Log::warning plus
     * DiagnosticManager) inside LifecycleFieldRegistry::persist(), so this
     * message is deliberately generic: the driver reads it in a flash, and
     * the handler's raw exception message must not leak to the field.
     */
    public static function criticalFieldFailed(\Throwable $previous): self
    {
        return new self(__('driver.error_critical_field_failed'), 0, $previous);
    }
}
