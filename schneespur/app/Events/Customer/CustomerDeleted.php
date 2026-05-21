<?php

namespace App\Events\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Customer $customer,
    ) {}
}
