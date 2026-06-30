<?php

namespace App\Enums;

enum LifecyclePoint: string
{
    case ShiftStart = 'shift.start';
    case ShiftEnd = 'shift.end';
    case JobStart = 'job.start';
    case JobEnd = 'job.end';
}
