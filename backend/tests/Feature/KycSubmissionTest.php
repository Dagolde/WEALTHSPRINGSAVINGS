<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KycSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function user_can_submit_kyc_document()
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('kyc_document.jpg', 2048, 'image/jpeg'); // 2MB

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC document submitted successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'kyc_status',
                    'kyc_document_url',
                    'submitted_at',
                ],
            ]);

        // Verify user's KYC status was updated
        $user->refresh();
        $this->assertEquals('pending', $user->kyc_status);
        $this->assertNotNull($user->kyc_document_url);
        $this->assertStringContainsString('kyc_documents/user_' . $user->id, $user->kyc_document_url);

        // Verify file was stored
        Storage::disk('local')->assertExists($user->kyc_document_url);
    }

    /** @test */
    public function user_can_submit_kyc_document_as_pdf()
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('kyc_document.pdf', 2048, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertStringEndsWith('.pdf', $user->kyc_document_url);
    }

    /** @test */
    public function kyc_submission_requires_authentication()
    {
        $file = UploadedFile::fake()->create('kyc_document.jpg', 100, 'image/jpeg');

        $response = $this->postJson('/api/v1/user/kyc/submit', [
            'document' => $file,
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function kyc_submission_validates_file_type()
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function kyc_submission_validates_file_size()
    {
        $user = User::factory()->create();

        // Create a file larger than 5MB
        $file = UploadedFile::fake()->create('kyc_document.jpg', 6000, 'image/jpeg'); // 6MB

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function kyc_submission_requires_document_field()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    /** @test */
    public function verified_user_cannot_resubmit_kyc()
    {
        $user = User::factory()->create([
            'kyc_status' => 'verified',
        ]);

        $file = UploadedFile::fake()->create('kyc_document.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Your KYC is already verified',
            ]);
    }

    /** @test */
    public function rejected_user_can_resubmit_kyc()
    {
        $user = User::factory()->create([
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => 'Document not clear',
        ]);

        $file = UploadedFile::fake()->create('kyc_document.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/user/kyc/submit', [
                'document' => $file,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('pending', $user->kyc_status);
        $this->assertNull($user->kyc_rejection_reason);
    }

    /** @test */
    public function user_can_get_kyc_status()
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
            'kyc_document_url' => 'kyc_documents/user_1_1234567890.jpg',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/kyc/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC status retrieved successfully',
                'data' => [
                    'kyc_status' => 'pending',
                    'kyc_document_url' => 'kyc_documents/user_1_1234567890.jpg',
                    'kyc_rejection_reason' => null,
                ],
            ]);
    }

    /** @test */
    public function user_can_see_rejection_reason_in_kyc_status()
    {
        $user = User::factory()->create([
            'kyc_status' => 'rejected',
            'kyc_document_url' => 'kyc_documents/user_1_1234567890.jpg',
            'kyc_rejection_reason' => 'Document is not clear enough',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user/kyc/status');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'kyc_status' => 'rejected',
                    'kyc_rejection_reason' => 'Document is not clear enough',
                ],
            ]);
    }

    /** @test */
    public function kyc_status_requires_authentication()
    {
        $response = $this->getJson('/api/v1/user/kyc/status');

        $response->assertStatus(401);
    }
}
