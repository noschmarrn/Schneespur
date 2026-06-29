<?php

namespace Tests\Feature\Extension;

use App\Enums\JobType;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Extension\JobTypeRegistry;
use App\ValueObjects\JobTypeValue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JobTypeCastTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_type_reads_as_value_object_with_label(): void
    {
        app(JobTypeRegistry::class)->registerType('raumen', 'job.type_raumen');

        $user = User::create(['name' => 'D', 'email' => 'd@test.local', 'password' => Hash::make('x')]);
        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);
        $shift = WorkShift::create(['user_id' => $user->id, 'started_at' => now()]);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'user_id' => $user->id,
            'type' => 'raumen',
            'started_at' => now(),
            'is_manual' => false,
        ]);

        $fresh = $job->fresh();
        $this->assertInstanceOf(JobTypeValue::class, $fresh->type);
        $this->assertSame('raumen', $fresh->type->value);
        $this->assertSame(__('job.type_raumen'), $fresh->type->label());
    }

    public function test_set_accepts_enum_and_value_object(): void
    {
        $user = User::create(['name' => 'D', 'email' => 'd2@test.local', 'password' => Hash::make('x')]);
        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);
        $shift = WorkShift::create(['user_id' => $user->id, 'started_at' => now()]);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'user_id' => $user->id,
            'type' => JobType::Streuen,
            'started_at' => now(),
            'is_manual' => false,
        ]);

        $this->assertSame('streuen', $job->fresh()->getRawOriginal('type'));
    }
}
