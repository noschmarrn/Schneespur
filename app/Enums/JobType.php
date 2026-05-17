<?php

namespace App\Enums;

enum JobType: string
{
    case Raumen = 'raumen';
    case Streuen = 'streuen';
    case Kontrolle = 'kontrolle';
    case RaumenStreuen = 'raumen_streuen';

    public function label(): string
    {
        return __('job.type_' . $this->value);
    }
}
