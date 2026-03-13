<?php

namespace Tests\Unit;

use App\Models\Payout;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_group()
    {
        $group = Group::factory()->create();
        $payout = Payout::factory()->forGroup($group)->create();

        $this->assertInstanceOf(Group::class, $payout->group);
        $this->assertEquals($group->id, $payout->group->id);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $payout = Payout::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $payout->user);
        $this->assertEquals($user->id, $payout->user->id);
    }

    /** @test */
    public function it_casts_amount_as_decimal()
    {
        $payout = Payout::factory()->create(['amount' => 5000.75]);

        $this->assertIsString($payout->amount);
        $this->assertEquals('5000.75', $payout->amount);
    }

    /** @test */
    public function it_casts_payout_date_as_date()
    {
        $payout = Payout::factory()->create([
            'payout_date' => '2024-01-15',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->payout_date);
        $this->assertEquals('2024-01-15', $payout->payout_date->format('Y-m-d'));
    }

    /** @test */
    public function it_casts_processed_at_as_datetime()
    {
        $payout = Payout::factory()->successful()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->processed_at);
    }

    /** @test */
    public function it_casts_payout_day_as_integer()
    {
        $payout = Payout::factory()->create(['payout_day' => 5]);

        $this->assertIsInt($payout->payout_day);
        $this->assertEquals(5, $payout->payout_day);
    }

    /** @test */
    public function scope_by_status_filters_by_given_status()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->count(2)->create();
        Payout::factory()->failed()->create();

        $payouts = Payout::byStatus('successful')->get();

        $this->assertCount(2, $payouts);
        $payouts->each(function ($payout) {
            $this->assertEquals('successful', $payout->status);
        });
    }

    /** @test */
    public function scope_by_date_filters_by_payout_date()
    {
        $date = '2024-01-15';
        Payout::factory()->onDate($date)->count(2)->create();
        Payout::factory()->onDate('2024-01-16')->create();

        $payouts = Payout::byDate($date)->get();

        $this->assertCount(2, $payouts);
        $payouts->each(function ($payout) use ($date) {
            $this->assertEquals($date, $payout->payout_date->format('Y-m-d'));
        });
    }

    /** @test */
    public function scope_pending_filters_pending_payouts()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();
        Payout::factory()->failed()->create();

        $pendingPayouts = Payout::pending()->get();

        $this->assertCount(1, $pendingPayouts);
        $this->assertEquals('pending', $pendingPayouts->first()->status);
    }

    /** @test */
    public function scope_successful_filters_successful_payouts()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();
        Payout::factory()->failed()->create();

        $successfulPayouts = Payout::successful()->get();

        $this->assertCount(1, $successfulPayouts);
        $this->assertEquals('successful', $successfulPayouts->first()->status);
    }

    /** @test */
    public function scope_failed_filters_failed_payouts()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();
        Payout::factory()->failed()->create();

        $failedPayouts = Payout::failed()->get();

        $this->assertCount(1, $failedPayouts);
        $this->assertEquals('failed', $failedPayouts->first()->status);
    }

    /** @test */
    public function scope_processed_filters_payouts_with_processed_at()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();
        Payout::factory()->failed()->create();

        $processedPayouts = Payout::processed()->get();

        $this->assertCount(2, $processedPayouts);
        $processedPayouts->each(function ($payout) {
            $this->assertNotNull($payout->processed_at);
        });
    }

    /** @test */
    public function scope_unprocessed_filters_payouts_without_processed_at()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();

        $unprocessedPayouts = Payout::unprocessed()->get();

        $this->assertCount(2, $unprocessedPayouts);
        $unprocessedPayouts->each(function ($payout) {
            $this->assertNull($payout->processed_at);
        });
    }

    /** @test */
    public function mark_as_processing_updates_status()
    {
        $payout = Payout::factory()->pending()->create();

        $this->assertEquals('pending', $payout->status);

        $payout->markAsProcessing();
        $payout->refresh();

        $this->assertEquals('processing', $payout->status);
    }

    /** @test */
    public function mark_as_successful_updates_status_and_processed_at()
    {
        $payout = Payout::factory()->pending()->create();

        $this->assertNull($payout->processed_at);
        $this->assertEquals('pending', $payout->status);

        $payout->markAsSuccessful();
        $payout->refresh();

        $this->assertEquals('successful', $payout->status);
        $this->assertNotNull($payout->processed_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->processed_at);
    }

    /** @test */
    public function mark_as_failed_updates_status_failure_reason_and_processed_at()
    {
        $payout = Payout::factory()->pending()->create();
        $failureReason = 'Insufficient funds in bank account';

        $this->assertNull($payout->processed_at);
        $this->assertNull($payout->failure_reason);
        $this->assertEquals('pending', $payout->status);

        $payout->markAsFailed($failureReason);
        $payout->refresh();

        $this->assertEquals('failed', $payout->status);
        $this->assertEquals($failureReason, $payout->failure_reason);
        $this->assertNotNull($payout->processed_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->processed_at);
    }

    /** @test */
    public function scopes_can_be_chained()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $date = '2024-01-15';

        // Create payout matching all filters
        Payout::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate($date)
            ->successful()
            ->create();

        // Create payout with different user (same group and date)
        Payout::factory()
            ->forGroup($group)
            ->forUser($user2)
            ->onDate($date)
            ->pending()
            ->create();

        // Create payout with different date (same group and user)
        Payout::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate('2024-01-16')
            ->successful()
            ->create();

        $payouts = Payout::forGroup($group->id)
            ->forUser($user->id)
            ->byDate($date)
            ->successful()
            ->get();

        $this->assertCount(1, $payouts);
        $this->assertEquals($group->id, $payouts->first()->group_id);
        $this->assertEquals($user->id, $payouts->first()->user_id);
        $this->assertEquals($date, $payouts->first()->payout_date->format('Y-m-d'));
        $this->assertEquals('successful', $payouts->first()->status);
    }

    /** @test */
    public function payout_reference_is_unique()
    {
        $reference = 'PAYOUT-UNIQUE-123';
        Payout::factory()->create(['payout_reference' => $reference]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Payout::factory()->create(['payout_reference' => $reference]);
    }

    /** @test */
    public function fillable_properties_are_mass_assignable()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        $data = [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'amount' => 10000.00,
            'payout_day' => 5,
            'payout_date' => '2024-01-15',
            'status' => 'pending',
            'payout_method' => 'wallet',
            'payout_reference' => 'REF-123',
            'failure_reason' => null,
            'processed_at' => null,
        ];

        $payout = Payout::create($data);

        $this->assertEquals($group->id, $payout->group_id);
        $this->assertEquals($user->id, $payout->user_id);
        $this->assertEquals('10000.00', $payout->amount);
        $this->assertEquals(5, $payout->payout_day);
        $this->assertEquals('2024-01-15', $payout->payout_date->format('Y-m-d'));
        $this->assertEquals('pending', $payout->status);
        $this->assertEquals('wallet', $payout->payout_method);
        $this->assertEquals('REF-123', $payout->payout_reference);
    }

    /** @test */
    public function timestamps_are_automatically_managed()
    {
        $payout = Payout::factory()->create();

        $this->assertNotNull($payout->created_at);
        $this->assertNotNull($payout->updated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payout->updated_at);
    }

    /** @test */
    public function user_can_have_multiple_payouts()
    {
        $user = User::factory()->create();
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        $payout1 = Payout::factory()->forUser($user)->forGroup($group1)->create();
        $payout2 = Payout::factory()->forUser($user)->forGroup($group2)->create();

        $userPayouts = Payout::forUser($user->id)->get();

        $this->assertCount(2, $userPayouts);
        $this->assertTrue($userPayouts->contains($payout1));
        $this->assertTrue($userPayouts->contains($payout2));
    }

    /** @test */
    public function group_can_have_multiple_payouts()
    {
        $group = Group::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $payout1 = Payout::factory()->forGroup($group)->forUser($user1)->create();
        $payout2 = Payout::factory()->forGroup($group)->forUser($user2)->create();

        $groupPayouts = Payout::forGroup($group->id)->get();

        $this->assertCount(2, $groupPayouts);
        $this->assertTrue($groupPayouts->contains($payout1));
        $this->assertTrue($groupPayouts->contains($payout2));
    }

    /** @test */
    public function processed_scope_includes_both_successful_and_failed_payouts()
    {
        Payout::factory()->pending()->create();
        Payout::factory()->successful()->create();
        Payout::factory()->failed()->create();

        $processedPayouts = Payout::processed()->get();

        $this->assertCount(2, $processedPayouts);
        $statuses = $processedPayouts->pluck('status')->toArray();
        $this->assertContains('successful', $statuses);
        $this->assertContains('failed', $statuses);
    }

    /** @test */
    public function multiple_payouts_can_exist_for_same_group_on_different_dates()
    {
        $group = Group::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $payout1 = Payout::factory()
            ->forGroup($group)
            ->forUser($user1)
            ->onDate('2024-01-15')
            ->create();

        $payout2 = Payout::factory()
            ->forGroup($group)
            ->forUser($user2)
            ->onDate('2024-01-16')
            ->create();

        $this->assertNotEquals($payout1->id, $payout2->id);
        $this->assertEquals($group->id, $payout1->group_id);
        $this->assertEquals($group->id, $payout2->group_id);
    }
}
