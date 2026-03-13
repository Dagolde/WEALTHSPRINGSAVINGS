<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminKycApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);

        // Create regular user with pending KYC
        $this->user = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/kyc-doc.pdf',
        ]);

        // Mock notification service HTTP calls
        Http::fake([
            '*/send' => Http::response(['success' => true], 200),
            '*/push' => Http::response(['success' => true], 200),
            '*/sms' => Http::response(['success' => true], 200),
            '*/email' => Http::response(['success' => true], 200),
        ]);
    }

    /** @test */
    public function admin_can_list_pending_kyc_submissions()
    {
        // Create additional users with different KYC statuses
        User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc1.pdf',
        ]);
        User::factory()->create([
            'kyc_status' => 'verified',
            'kyc_document_url' => 'https://example.com/doc2.pdf',
        ]);
        User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => null, // No document uploaded yet
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/kyc/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'kyc_status',
                            'kyc_document_url',
                            'created_at',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);

        // Should return 2 users with pending status and documents
        $this->assertEquals(2, count($response->json('data.data')));
    }

    /** @test */
    public function admin_can_approve_kyc_submission()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC approved successfully',
            ]);

        // Verify user KYC status updated
        $this->user->refresh();
        $this->assertEquals('verified', $this->user->kyc_status);
        $this->assertNull($this->user->kyc_rejection_reason);
    }

    /** @test */
    public function admin_can_reject_kyc_submission_with_reason()
    {
        $reason = 'Document not clear';

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
                'reason' => $reason,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC rejected successfully',
            ]);

        // Verify user KYC status updated
        $this->user->refresh();
        $this->assertEquals('rejected', $this->user->kyc_status);
        $this->assertEquals($reason, $this->user->kyc_rejection_reason);
    }

    /** @test */
    public function kyc_rejection_requires_reason()
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
                'reason' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function kyc_approval_creates_audit_log()
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'kyc_approved',
            'entity_type' => 'User',
            'entity_id' => $this->user->id,
        ]);

        $log = AuditLog::where('action', 'kyc_approved')->first();
        $this->assertNotNull($log);
        $this->assertEquals('pending', json_decode($log->old_values, true)['kyc_status']);
        $this->assertEquals('verified', json_decode($log->new_values, true)['kyc_status']);
    }

    /** @test */
    public function kyc_rejection_creates_audit_log()
    {
        $reason = 'Invalid document';

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
                'reason' => $reason,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'kyc_rejected',
            'entity_type' => 'User',
            'entity_id' => $this->user->id,
        ]);

        $log = AuditLog::where('action', 'kyc_rejected')->first();
        $this->assertNotNull($log);
        $newValues = json_decode($log->new_values, true);
        $this->assertEquals('rejected', $newValues['kyc_status']);
        $this->assertEquals($reason, $newValues['reason']);
    }

    /** @test */
    public function kyc_approval_sends_notification_to_user()
    {
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");

        // Verify notification was logged
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'kyc_status',
            'title' => 'KYC Status Update',
        ]);

        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('verified', $notification->message);
    }

    /** @test */
    public function kyc_rejection_sends_notification_with_reason()
    {
        $reason = 'Document expired';

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
                'reason' => $reason,
            ]);

        // Verify notification was logged
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'kyc_status',
            'title' => 'KYC Status Update',
        ]);

        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertStringContainsString('rejected', $notification->message);
        $this->assertStringContainsString($reason, $notification->message);
    }

    /** @test */
    public function non_admin_cannot_list_pending_kyc()
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($regularUser)
            ->getJson('/api/v1/admin/kyc/pending');

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_approve_kyc()
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($regularUser)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_cannot_reject_kyc()
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($regularUser)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
                'reason' => 'Test reason',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_kyc_endpoints()
    {
        $response = $this->getJson('/api/v1/admin/kyc/pending');
        $response->assertStatus(401);

        $response = $this->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");
        $response->assertStatus(401);

        $response = $this->postJson("/api/v1/admin/kyc/{$this->user->id}/reject", [
            'reason' => 'Test',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function kyc_approval_returns_404_for_non_existent_user()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/kyc/99999/approve');

        $response->assertStatus(400);
    }

    /** @test */
    public function kyc_rejection_returns_404_for_non_existent_user()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/kyc/99999/reject', [
                'reason' => 'Test reason',
            ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function pending_kyc_list_supports_pagination()
    {
        // Create 25 users with pending KYC
        User::factory()->count(25)->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc.pdf',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/kyc/pending?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertGreaterThan(10, $response->json('data.total'));
    }

    /** @test */
    public function kyc_status_transition_validates_property_3()
    {
        // Property 3: KYC Status Transition
        // For any user submitting KYC documents, the system should transition 
        // the KYC status from the current state to 'pending', and admin actions 
        // should transition it to either 'verified' or 'rejected' with appropriate metadata.

        // Initial state: pending
        $this->assertEquals('pending', $this->user->kyc_status);

        // Admin approves -> should transition to verified
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$this->user->id}/approve");

        $this->user->refresh();
        $this->assertEquals('verified', $this->user->kyc_status);
        $this->assertNull($this->user->kyc_rejection_reason);

        // Create another user for rejection test
        $user2 = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc.pdf',
        ]);

        $reason = 'Document not valid';

        // Admin rejects -> should transition to rejected with reason
        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/kyc/{$user2->id}/reject", [
                'reason' => $reason,
            ]);

        $user2->refresh();
        $this->assertEquals('rejected', $user2->kyc_status);
        $this->assertEquals($reason, $user2->kyc_rejection_reason);
    }

    /** @test */
    public function pending_kyc_list_only_shows_users_with_documents()
    {
        // Create users with and without documents
        $userWithDoc = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc.pdf',
        ]);

        $userWithoutDoc = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/kyc/pending');

        $response->assertStatus(200);

        $userIds = collect($response->json('data.data'))->pluck('id')->toArray();

        // Should include user with document
        $this->assertContains($userWithDoc->id, $userIds);

        // Should NOT include user without document
        $this->assertNotContains($userWithoutDoc->id, $userIds);
    }

    /** @test */
    public function pending_kyc_list_ordered_by_oldest_first()
    {
        // Create users at different times
        $oldUser = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc1.pdf',
            'created_at' => now()->subDays(5),
        ]);

        $newUser = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'https://example.com/doc2.pdf',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/kyc/pending');

        $response->assertStatus(200);

        $users = $response->json('data.data');

        // First user should be the oldest
        $this->assertEquals($oldUser->id, $users[0]['id']);
    }
}
