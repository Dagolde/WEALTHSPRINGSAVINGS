<?php

namespace Tests\Unit;

use App\Models\Contribution;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_belongs_to_a_group()
    {
        $group = Group::factory()->create();
        $contribution = Contribution::factory()->forGroup($group)->create();

        $this->assertInstanceOf(Group::class, $contribution->group);
        $this->assertEquals($group->id, $contribution->group->id);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $contribution = Contribution::factory()->forUser($user)->create();

        $this->assertInstanceOf(User::class, $contribution->user);
        $this->assertEquals($user->id, $contribution->user->id);
    }

    /** @test */
    public function it_casts_amount_as_decimal()
    {
        $contribution = Contribution::factory()->create(['amount' => 1000.50]);

        $this->assertIsString($contribution->amount);
        $this->assertEquals('1000.50', $contribution->amount);
    }

    /** @test */
    public function it_casts_contribution_date_as_date()
    {
        $contribution = Contribution::factory()->create([
            'contribution_date' => '2024-01-15',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $contribution->contribution_date);
        $this->assertEquals('2024-01-15', $contribution->contribution_date->format('Y-m-d'));
    }

    /** @test */
    public function it_casts_paid_at_as_datetime()
    {
        $contribution = Contribution::factory()->successful()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $contribution->paid_at);
    }

    /** @test */
    public function scope_pending_filters_pending_contributions()
    {
        Contribution::factory()->pending()->create();
        Contribution::factory()->successful()->create();
        Contribution::factory()->failed()->create();

        $pendingContributions = Contribution::pending()->get();

        $this->assertCount(1, $pendingContributions);
        $this->assertEquals('pending', $pendingContributions->first()->payment_status);
    }

    /** @test */
    public function scope_successful_filters_successful_contributions()
    {
        Contribution::factory()->pending()->create();
        Contribution::factory()->successful()->create();
        Contribution::factory()->failed()->create();

        $successfulContributions = Contribution::successful()->get();

        $this->assertCount(1, $successfulContributions);
        $this->assertEquals('successful', $successfulContributions->first()->payment_status);
    }

    /** @test */
    public function scope_failed_filters_failed_contributions()
    {
        Contribution::factory()->pending()->create();
        Contribution::factory()->successful()->create();
        Contribution::factory()->failed()->create();

        $failedContributions = Contribution::failed()->get();

        $this->assertCount(1, $failedContributions);
        $this->assertEquals('failed', $failedContributions->first()->payment_status);
    }

    /** @test */
    public function scope_by_status_filters_by_given_status()
    {
        Contribution::factory()->pending()->create();
        Contribution::factory()->successful()->count(2)->create();

        $contributions = Contribution::byStatus('successful')->get();

        $this->assertCount(2, $contributions);
        $contributions->each(function ($contribution) {
            $this->assertEquals('successful', $contribution->payment_status);
        });
    }

    /** @test */
    public function scope_by_date_filters_by_contribution_date()
    {
        $date = '2024-01-15';
        Contribution::factory()->onDate($date)->count(2)->create();
        Contribution::factory()->onDate('2024-01-16')->create();

        $contributions = Contribution::byDate($date)->get();

        $this->assertCount(2, $contributions);
        $contributions->each(function ($contribution) use ($date) {
            $this->assertEquals($date, $contribution->contribution_date->format('Y-m-d'));
        });
    }

    /** @test */
    public function scope_between_dates_filters_by_date_range()
    {
        Contribution::factory()->onDate('2024-01-10')->create();
        Contribution::factory()->onDate('2024-01-15')->create();
        Contribution::factory()->onDate('2024-01-20')->create();
        Contribution::factory()->onDate('2024-01-25')->create();

        $contributions = Contribution::betweenDates('2024-01-12', '2024-01-22')->get();

        $this->assertCount(2, $contributions);
    }

    /** @test */
    public function scope_for_group_filters_by_group_id()
    {
        $group = Group::factory()->create();
        Contribution::factory()->forGroup($group)->count(3)->create();
        Contribution::factory()->count(2)->create();

        $contributions = Contribution::forGroup($group->id)->get();

        $this->assertCount(3, $contributions);
        $contributions->each(function ($contribution) use ($group) {
            $this->assertEquals($group->id, $contribution->group_id);
        });
    }

    /** @test */
    public function scope_for_user_filters_by_user_id()
    {
        $user = User::factory()->create();
        Contribution::factory()->forUser($user)->count(3)->create();
        Contribution::factory()->count(2)->create();

        $contributions = Contribution::forUser($user->id)->get();

        $this->assertCount(3, $contributions);
        $contributions->each(function ($contribution) use ($user) {
            $this->assertEquals($user->id, $contribution->user_id);
        });
    }

    /** @test */
    public function is_pending_returns_true_for_pending_contribution()
    {
        $contribution = Contribution::factory()->pending()->create();

        $this->assertTrue($contribution->isPending());
        $this->assertFalse($contribution->isSuccessful());
        $this->assertFalse($contribution->isFailed());
    }

    /** @test */
    public function is_successful_returns_true_for_successful_contribution()
    {
        $contribution = Contribution::factory()->successful()->create();

        $this->assertTrue($contribution->isSuccessful());
        $this->assertFalse($contribution->isPending());
        $this->assertFalse($contribution->isFailed());
    }

    /** @test */
    public function is_failed_returns_true_for_failed_contribution()
    {
        $contribution = Contribution::factory()->failed()->create();

        $this->assertTrue($contribution->isFailed());
        $this->assertFalse($contribution->isPending());
        $this->assertFalse($contribution->isSuccessful());
    }

    /** @test */
    public function mark_as_successful_updates_status_and_paid_at()
    {
        $contribution = Contribution::factory()->pending()->create();

        $this->assertNull($contribution->paid_at);
        $this->assertEquals('pending', $contribution->payment_status);

        $contribution->markAsSuccessful();
        $contribution->refresh();

        $this->assertEquals('successful', $contribution->payment_status);
        $this->assertNotNull($contribution->paid_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $contribution->paid_at);
    }

    /** @test */
    public function mark_as_failed_updates_status()
    {
        $contribution = Contribution::factory()->pending()->create();

        $this->assertEquals('pending', $contribution->payment_status);

        $contribution->markAsFailed();
        $contribution->refresh();

        $this->assertEquals('failed', $contribution->payment_status);
    }

    /** @test */
    public function scopes_can_be_chained()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $date = '2024-01-15';

        // Create contribution matching all filters
        Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate($date)
            ->successful()
            ->create();

        // Create contribution with different user (same group and date)
        Contribution::factory()
            ->forGroup($group)
            ->forUser($user2)
            ->onDate($date)
            ->pending()
            ->create();

        // Create contribution with different date (same group and user)
        Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate('2024-01-16')
            ->successful()
            ->create();

        $contributions = Contribution::forGroup($group->id)
            ->forUser($user->id)
            ->byDate($date)
            ->successful()
            ->get();

        $this->assertCount(1, $contributions);
        $this->assertEquals($group->id, $contributions->first()->group_id);
        $this->assertEquals($user->id, $contributions->first()->user_id);
        $this->assertEquals($date, $contributions->first()->contribution_date->format('Y-m-d'));
        $this->assertEquals('successful', $contributions->first()->payment_status);
    }

    /** @test */
    public function payment_reference_is_unique()
    {
        $reference = 'PAY-UNIQUE-123';
        Contribution::factory()->create(['payment_reference' => $reference]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Contribution::factory()->create(['payment_reference' => $reference]);
    }

    /** @test */
    public function contribution_date_group_and_user_combination_is_unique()
    {
        $group = Group::factory()->create();
        $user = User::factory()->create();
        $date = '2024-01-15';

        Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate($date)
            ->create();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate($date)
            ->create();
    }

    /** @test */
    public function user_can_contribute_to_different_groups_on_same_date()
    {
        $user = User::factory()->create();
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();
        $date = '2024-01-15';

        $contribution1 = Contribution::factory()
            ->forGroup($group1)
            ->forUser($user)
            ->onDate($date)
            ->create();

        $contribution2 = Contribution::factory()
            ->forGroup($group2)
            ->forUser($user)
            ->onDate($date)
            ->create();

        $this->assertNotEquals($contribution1->id, $contribution2->id);
        $this->assertEquals($user->id, $contribution1->user_id);
        $this->assertEquals($user->id, $contribution2->user_id);
    }

    /** @test */
    public function user_can_contribute_to_same_group_on_different_dates()
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $contribution1 = Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate('2024-01-15')
            ->create();

        $contribution2 = Contribution::factory()
            ->forGroup($group)
            ->forUser($user)
            ->onDate('2024-01-16')
            ->create();

        $this->assertNotEquals($contribution1->id, $contribution2->id);
        $this->assertEquals($group->id, $contribution1->group_id);
        $this->assertEquals($group->id, $contribution2->group_id);
    }
}

