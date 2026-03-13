<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserController extends ApiController
{
    /**
     * Submit KYC document for verification.
     */
    #[OA\Post(
        path: '/api/v1/user/kyc/submit',
        summary: 'Submit KYC document for verification',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['document'],
                    properties: [
                        new OA\Property(
                            property: 'document',
                            type: 'string',
                            format: 'binary',
                            description: 'KYC document (image or PDF, max 5MB)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'KYC document submitted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'KYC document submitted successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'kyc_status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'kyc_document_url', type: 'string', example: 'kyc_documents/user_1_1234567890.jpg'),
                                new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            example: ['document' => ['The document must be a file of type: jpg, jpeg, png, pdf.']]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function submitKyc(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB in kilobytes
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = $request->user();

        // Check if KYC is already verified
        if ($user->kyc_status === 'verified') {
            return $this->errorResponse('Your KYC is already verified', 422);
        }

        try {
            // Get the uploaded file
            $file = $request->file('document');
            
            // Generate a unique filename
            $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Store the file in storage/app/kyc_documents
            $path = $file->storeAs('kyc_documents', $filename);

            // Update user's KYC status and document URL
            $user->update([
                'kyc_status' => 'pending',
                'kyc_document_url' => $path,
                'kyc_rejection_reason' => null, // Clear any previous rejection reason
            ]);

            return $this->successResponse(
                [
                    'kyc_status' => $user->kyc_status,
                    'kyc_document_url' => $user->kyc_document_url,
                    'submitted_at' => $user->updated_at->toIso8601String(),
                ],
                'KYC document submitted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload KYC document. Please try again.', 500);
        }
    }

    /**
     * Get KYC status and document information.
     */
    #[OA\Get(
        path: '/api/v1/user/kyc/status',
        summary: 'Get KYC status and document information',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KYC status retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'KYC status retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'kyc_status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'kyc_document_url', type: 'string', nullable: true, example: 'kyc_documents/user_1_1234567890.jpg'),
                                new OA\Property(property: 'kyc_rejection_reason', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time', nullable: true, example: '2024-01-15T10:30:00Z'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function getKycStatus(Request $request)
    {
        $user = $request->user();

        return $this->successResponse(
            [
                'kyc_status' => $user->kyc_status,
                'kyc_document_url' => $user->kyc_document_url,
                'kyc_rejection_reason' => $user->kyc_rejection_reason,
                'submitted_at' => $user->kyc_document_url ? $user->updated_at->toIso8601String() : null,
                'verified_at' => $user->kyc_status === 'verified' ? $user->updated_at->toIso8601String() : null,
            ],
            'KYC status retrieved successfully'
        );
    }

    /**
     * Add a new bank account for the user.
     */
    #[OA\Post(
        path: '/api/v1/user/bank-account',
        summary: 'Add a new bank account',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['account_name', 'account_number', 'bank_name', 'bank_code'],
                    properties: [
                        new OA\Property(property: 'account_name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'account_number', type: 'string', example: '0123456789'),
                        new OA\Property(property: 'bank_name', type: 'string', example: 'First Bank'),
                        new OA\Property(property: 'bank_code', type: 'string', example: '011'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bank account added successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bank account added successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'account_name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'account_number', type: 'string', example: '0123456789'),
                                new OA\Property(property: 'bank_name', type: 'string', example: 'First Bank'),
                                new OA\Property(property: 'bank_code', type: 'string', example: '011'),
                                new OA\Property(property: 'is_verified', type: 'boolean', example: false),
                                new OA\Property(property: 'is_primary', type: 'boolean', example: true),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function addBankAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:20'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_code' => ['required', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        $user = $request->user();

        // Check for duplicate account (same account_number + bank_code for user)
        $existingAccount = $user->bankAccounts()
            ->where('account_number', $request->account_number)
            ->where('bank_code', $request->bank_code)
            ->first();

        if ($existingAccount) {
            return $this->errorResponse('This bank account is already linked to your profile', 422);
        }

        // Check if this is the user's first bank account
        $isFirstAccount = $user->bankAccounts()->count() === 0;

        // Create the bank account
        $bankAccount = $user->bankAccounts()->create([
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code,
            'is_verified' => false,
            'is_primary' => $isFirstAccount, // Set first account as primary
        ]);

        return $this->successResponse(
            [
                'id' => $bankAccount->id,
                'account_name' => $bankAccount->account_name,
                'account_number' => $bankAccount->account_number,
                'bank_name' => $bankAccount->bank_name,
                'bank_code' => $bankAccount->bank_code,
                'is_verified' => $bankAccount->is_verified,
                'is_primary' => $bankAccount->is_primary,
            ],
            'Bank account added successfully'
        );
    }

    /**
     * Get all bank accounts for the user.
     */
    #[OA\Get(
        path: '/api/v1/user/bank-accounts',
        summary: 'Get all bank accounts for the user',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bank accounts retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bank accounts retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'account_name', type: 'string', example: 'John Doe'),
                                    new OA\Property(property: 'account_number', type: 'string', example: '0123456789'),
                                    new OA\Property(property: 'bank_name', type: 'string', example: 'First Bank'),
                                    new OA\Property(property: 'bank_code', type: 'string', example: '011'),
                                    new OA\Property(property: 'is_verified', type: 'boolean', example: false),
                                    new OA\Property(property: 'is_primary', type: 'boolean', example: true),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
        ]
    )]
    public function getBankAccounts(Request $request)
    {
        $user = $request->user();
        $bankAccounts = $user->bankAccounts()->get();

        $data = $bankAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank_name,
                'bank_code' => $account->bank_code,
                'is_verified' => $account->is_verified,
                'is_primary' => $account->is_primary,
            ];
        });

        return $this->successResponse(
            $data,
            'Bank accounts retrieved successfully'
        );
    }

    /**
     * Get the authenticated user's profile.
     */
    #[OA\Get(
        path: '/api/v1/user/profile',
        summary: 'Get user profile',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+2348012345678'),
                                new OA\Property(property: 'kyc_status', type: 'string', example: 'verified'),
                                new OA\Property(property: 'wallet_balance', type: 'string', example: '1000.00'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function getProfile(Request $request)
    {
        $user = $request->user();

        return $this->successResponse(
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'kyc_status' => $user->kyc_status,
                'wallet_balance' => number_format($user->wallet_balance, 2, '.', ''),
                'status' => $user->status,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'Profile retrieved successfully'
        );
    }

    /**
     * Update the authenticated user's profile.
     */
    #[OA\Put(
        path: '/api/v1/user/profile',
        summary: 'Update user profile',
        security: [['sanctum' => []]],
        tags: ['User Management'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'John Updated'),
                        new OA\Property(property: 'phone', type: 'string', example: '+2348012345679'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile updated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Updated'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '+2348012345679'),
                                new OA\Property(property: 'kyc_status', type: 'string', example: 'verified'),
                                new OA\Property(property: 'wallet_balance', type: 'string', example: '1000.00'),
                                new OA\Property(property: 'status', type: 'string', example: 'active'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated'),
                    ]
                )
            ),
        ]
    )]
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $user->name,
            'phone' => $user->phone,
        ];

        // Update the user profile
        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);

        // Store new values for audit log
        $newValues = [
            'name' => $user->name,
            'phone' => $user->phone,
        ];

        // Log the profile change in audit_logs table
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'profile_updated',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'kyc_status' => $user->kyc_status,
                'wallet_balance' => number_format($user->wallet_balance, 2, '.', ''),
                'status' => $user->status,
            ],
            'Profile updated successfully'
        );
    }

    /**
     * Upload profile picture.
     */
    #[OA\Post(
        path: '/api/v1/user/profile/picture',
        summary: 'Upload profile picture',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['picture'],
                    properties: [
                        new OA\Property(
                            property: 'picture',
                            type: 'string',
                            format: 'binary',
                            description: 'Profile picture file (jpg, jpeg, png, max 5MB)'
                        ),
                    ]
                )
            )
        ),
        tags: ['User'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile picture uploaded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Profile picture uploaded successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'profile_picture_url', type: 'string', example: 'profile_pictures/user_1_1234567890.jpg'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                    ]
                )
            ),
        ]
    )]
    public function uploadProfilePicture(Request $request)
    {
        $user = $request->user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'picture' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // 5MB max
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        try {
            // Delete old profile picture if exists
            if ($user->profile_picture_url) {
                Storage::disk('public')->delete($user->profile_picture_url);
            }

            // Store the new profile picture in the public disk
            $file = $request->file('picture');
            $filename = 'user_'.$user->id.'_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('profile_pictures', $filename, 'public');

            // Update user profile picture URL
            $user->update([
                'profile_picture_url' => $path,
            ]);

            // Log the action
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'profile_picture_uploaded',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'old_values' => ['profile_picture_url' => $user->getOriginal('profile_picture_url')],
                'new_values' => ['profile_picture_url' => $path],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse(
                [
                    'profile_picture_url' => $path,
                ],
                'Profile picture uploaded successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload profile picture: '.$e->getMessage(), 500);
        }
    }
}
