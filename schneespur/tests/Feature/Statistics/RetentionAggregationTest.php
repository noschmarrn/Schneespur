<?php

namespace Tests\Feature\Statistics;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\MonthlyStatistic;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RetentionAggregationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeJob(string $type, User $user, CustomerObject $object): Job
    {
        $shift = WorkShift::create(['user_id' => $user->id, 'started_at' => Carbon::create(2026, 1, 10, 8)]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'customer_id' => $object->customer_id,
            'customer_object_id' => $object->id,
            'user_id' => $user->id,
            'type' => $type,
            'started_at' => Carbon::create(2026, 1, 10, 8),
            'ended_at' => Carbon::create(2026, 1, 10, 9),
            'is_manual' => false,
        ]);
    }

    public function test_aggregates_core_and_module_types_into_counts(): void
    {
        $user = User::create(['name' => 'D', 'email' => 'agg@test.local', 'password' => Hash::make('x')]);
        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);

        $jobs = collect([
            $this->makeJob('raumen', $user, $object),
            $this->makeJob('raumen', $user, $object),
            $this->makeJob('maehen', $user, $object),
        ])->each->refresh();

        app(RetentionService::class)->aggregateForMonth(2026, 1, $jobs);

        $stat = MonthlyStatistic::where('year', 2026)->where('month', 1)->first();
        $this->assertSame(['raumen' => 2, 'maehen' => 1], $stat->counts);
        $this->assertSame(3, $stat->total_jobs);
    }

    public function test_merges_into_existing_month_by_summing(): void
    {
        MonthlyStatistic::create([
            'year' => 2026, 'month' => 1, 'total_jobs' => 1,
            'counts' => ['raumen' => 1], 'manual_count' => 0,
        ]);

        $user = User::create(['name' => 'D', 'email' => 'agg2@test.local', 'password' => Hash::make('x')]);
        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);

        $jobs = collect([$this->makeJob('raumen', $user, $object)])->each->refresh();

        app(RetentionService::class)->aggregateForMonth(2026, 1, $jobs);

        $stat = MonthlyStatistic::where('year', 2026)->where('month', 1)->first();
        $this->assertSame(['raumen' => 2], $stat->counts);
        $this->assertSame(2, $stat->total_jobs);
    }
}
