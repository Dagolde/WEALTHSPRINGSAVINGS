<?php

namespace Tests\Property;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use App\Models\Contribution;
use App\Models\Payout;
use App\Models\BankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Eris\Generator;

/**
 * Property-based test for data serialization round-trip
 * 
 * **Validates: Property 20 - Data Serialization Round-Trip**
 * 
 * For any domain object (User, Group, Contribution, Payout), serializing to JSON 
 * and then deserializing should produce an equivalent object with the same field values.
 */
class DataSerializationPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property: User serialization round-trip preserves data
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function user_serialization_round_trip_preserves_data()
    {
        $this->forAll(
            Generator\string(),
            Generator\elements(['pending', 'verified', 'rejected']),
            Generator\float(0, 1000000)
        )->then(function ($name, $kycStatus, $walletBalance) {
            // Create user
            $user = User::factory()->create([
                'name' => $name,
                'kyc_status' => $kycStatus,
                'wallet_balance' => $walletBalance
            ]);

            // Serialize to JSON
            $serialized = json_encode($user->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify critical fields preserved
            $this->assertEquals($user->id, $deserialized['id']);
            $this->assertEquals($user->name, $deserialized['name']);
            $this->assertEquals($user->email, $deserialized['email']);
            $this->assertEquals($user->phone, $deserialized['phone']);
            $this->assertEquals($user->kyc_status, $deserialized['kyc_status']);
            $this->assertEquals(
                (float) $user->wallet_balance,
                (float) $deserialized['wallet_balance']
            );
        });
    }

    /**
     * Property: Group serialization round-trip preserves data
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function group_serialization_round_trip_preserves_data()
    {
        $this->forAll(
            Generator\string(),
            Generator\choose(100, 10000),
            Generator\choose(3, 50),
            Generator\choose(3, 365),
            Generator\elements(['pending', 'active', 'completed', 'cancelled'])
        )->then(function ($name, $amount, $members, $days, $status) {
            // Create group
            $group = Group::factory()->create([
                'name' => $name,
                'contribution_amount' => $amount,
                'total_members' => $members,
                'cycle_days' => $days,
                'status' => $status
            ]);

            // Serialize to JSON
            $serialized = json_encode($group->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify critical fields preserved
            $this->assertEquals($group->id, $deserialized['id']);
            $this->assertEquals($group->name, $deserialized['name']);
            $this->assertEquals($group->group_code, $deserialized['group_code']);
            $this->assertEquals(
                (float) $group->contribution_amount,
                (float) $deserialized['contribution_amount']
            );
            $this->assertEquals($group->total_members, $deserialized['total_members']);
            $this->assertEquals($group->cycle_days, $deserialized['cycle_days']);
            $this->assertEquals($group->status, $deserialized['status']);
        });
    }

    /**
     * Property: Contribution serialization round-trip preserves data
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function contribution_serialization_round_trip_preserves_data()
    {
        $this->forAll(
            Generator\choose(100, 10000),
            Generator\elements(['wallet', 'card', 'bank_transfer']),
            Generator\elements(['pending', 'successful', 'failed'])
        )->then(function ($amount, $paymentMethod, $paymentStatus) {
            // Create contribution
            $user = User::factory()->create();
            $group = Group::factory()->create();

            $contribution = Contribution::factory()->create([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus
            ]);

            // Serialize to JSON
            $serialized = json_encode($contribution->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify critical fields preserved
            $this->assertEquals($contribution->id, $deserialized['id']);
            $this->assertEquals($contribution->user_id, $deserialized['user_id']);
            $this->assertEquals($contribution->group_id, $deserialized['group_id']);
            $this->assertEquals(
                (float) $contribution->amount,
                (float) $deserialized['amount']
            );
            $this->assertEquals($contribution->payment_method, $deserialized['payment_method']);
            $this->assertEquals($contribution->payment_status, $deserialized['payment_status']);
            $this->assertEquals($contribution->payment_reference, $deserialized['payment_reference']);
        });
    }

    /**
     * Property: Payout serialization round-trip preserves data
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function payout_serialization_round_trip_preserves_data()
    {
        $this->forAll(
            Generator\choose(1000, 100000),
            Generator\choose(1, 30),
            Generator\elements(['pending', 'processing', 'successful', 'failed']),
            Generator\elements(['wallet', 'bank_transfer'])
        )->then(function ($amount, $payoutDay, $status, $payoutMethod) {
            // Create payout
            $user = User::factory()->create();
            $group = Group::factory()->create();

            $payout = Payout::factory()->create([
                'user_id' => $user->id,
                'group_id' => $group->id,
                'amount' => $amount,
                'payout_day' => $payoutDay,
                'status' => $status,
                'payout_method' => $payoutMethod
            ]);

            // Serialize to JSON
            $serialized = json_encode($payout->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify critical fields preserved
            $this->assertEquals($payout->id, $deserialized['id']);
            $this->assertEquals($payout->user_id, $deserialized['user_id']);
            $this->assertEquals($payout->group_id, $deserialized['group_id']);
            $this->assertEquals(
                (float) $payout->amount,
                (float) $deserialized['amount']
            );
            $this->assertEquals($payout->payout_day, $deserialized['payout_day']);
            $this->assertEquals($payout->status, $deserialized['status']);
            $this->assertEquals($payout->payout_method, $deserialized['payout_method']);
        });
    }

    /**
     * Property: Bank account serialization round-trip preserves data
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function bank_account_serialization_round_trip_preserves_data()
    {
        $this->forAll(
            Generator\string(),
            Generator\string(),
            Generator\bool(),
            Generator\bool()
        )->then(function ($accountName, $accountNumber, $isVerified, $isPrimary) {
            // Create bank account
            $user = User::factory()->create();

            $bankAccount = BankAccount::factory()->create([
                'user_id' => $user->id,
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'is_verified' => $isVerified,
                'is_primary' => $isPrimary
            ]);

            // Serialize to JSON
            $serialized = json_encode($bankAccount->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify critical fields preserved
            $this->assertEquals($bankAccount->id, $deserialized['id']);
            $this->assertEquals($bankAccount->user_id, $deserialized['user_id']);
            $this->assertEquals($bankAccount->account_name, $deserialized['account_name']);
            $this->assertEquals($bankAccount->account_number, $deserialized['account_number']);
            $this->assertEquals($bankAccount->bank_name, $deserialized['bank_name']);
            $this->assertEquals($bankAccount->bank_code, $deserialized['bank_code']);
            $this->assertEquals((bool) $bankAccount->is_verified, (bool) $deserialized['is_verified']);
            $this->assertEquals((bool) $bankAccount->is_primary, (bool) $deserialized['is_primary']);
        });
    }

    /**
     * Property: Complex nested object serialization preserves structure
     * 
     * @test
     * @group property
     * @group Feature: rotational-contribution-app, Property 20: Data Serialization Round-Trip
     */
    public function complex_nested_object_serialization_preserves_structure()
    {
        $this->forAll(
            Generator\choose(3, 5)  // Number of members
        )->then(function ($memberCount) {
            // Create group with members and contributions
            $group = Group::factory()->create([
                'status' => 'active',
                'contribution_amount' => 1000,
                'total_members' => $memberCount
            ]);

            $users = User::factory()->count($memberCount)->create();

            foreach ($users as $index => $user) {
                $group->members()->create([
                    'user_id' => $user->id,
                    'position_number' => $index + 1,
                    'payout_day' => $index + 1
                ]);

                $group->contributions()->create([
                    'user_id' => $user->id,
                    'amount' => 1000,
                    'payment_method' => 'wallet',
                    'payment_reference' => "PAY-{$user->id}",
                    'payment_status' => 'successful',
                    'contribution_date' => now()->toDateString()
                ]);
            }

            // Load with relationships
            $groupWithRelations = Group::with(['members', 'contributions'])->find($group->id);

            // Serialize to JSON
            $serialized = json_encode($groupWithRelations->toArray());
            $this->assertNotFalse($serialized, 'Serialization should succeed');

            // Deserialize from JSON
            $deserialized = json_decode($serialized, true);
            $this->assertIsArray($deserialized, 'Deserialization should succeed');

            // Verify structure preserved
            $this->assertArrayHasKey('members', $deserialized);
            $this->assertArrayHasKey('contributions', $deserialized);
            $this->assertCount($memberCount, $deserialized['members']);
            $this->assertCount($memberCount, $deserialized['contributions']);

            // Verify nested data integrity
            foreach ($deserialized['members'] as $index => $member) {
                $this->assertArrayHasKey('user_id', $member);
                $this->assertArrayHasKey('position_number', $member);
                $this->assertEquals($index + 1, $member['position_number']);
            }
        });
    }
}
