<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class AuthController extends ApiController
{
    /**
     * Register a new user.
     */
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a new user',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+2348012345678'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                        new OA\Property(property: 'phone', type: 'string', example: '+2348012345678'),
                                        new OA\Property(property: 'kyc_status', type: 'string', example: 'pending'),
                                        new OA\Property(property: 'wallet_balance', type: 'string', example: '0.00'),
                                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
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
                            example: ['email' => ['The email has already been taken.']]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function register(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        // Check for duplicate accounts (fraud detection)
        $fraudService = app(\App\Services\FraudDetectionService::class);
        $duplicateCheck = $fraudService->checkDuplicateAccounts(
            $request->email,
            $request->phone,
            $request->header('X-Device-ID'),
            $request->ip()
        );

        if ($duplicateCheck['duplicates_found'] > 0) {
            \Log::warning('Duplicate account detected during registration', [
                'email' => $request->email,
                'phone' => $request->phone,
                'duplicates' => $duplicateCheck['user_ids']
            ]);
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'kyc_status' => 'pending',
            'wallet_balance' => 0.00,
            'status' => 'active',
        ]);

        // Analyze user behavior after registration
        $fraudResult = $fraudService->analyzeUserBehavior($user->id);
        $fraudService->handleFraudResult($user->id, $fraudResult);

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return success response
        return $this->successResponse(
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'kyc_status' => $user->kyc_status,
                    'kyc_document_url' => $user->kyc_document_url,
                    'profile_picture_url' => $user->profile_picture_url,
                    'wallet_balance' => $user->wallet_balance,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                ],
                'token' => $token,
            ],
            'Registration successful',
            201
        );
    }

    /**
     * Login a user.
     */
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login a user',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                        new OA\Property(property: 'phone', type: 'string', example: '+2348012345678'),
                                        new OA\Property(property: 'kyc_status', type: 'string', example: 'pending'),
                                        new OA\Property(property: 'wallet_balance', type: 'string', example: '0.00'),
                                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Account suspended or inactive',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Your account has been suspended'),
                    ]
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many login attempts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Too many login attempts. Please try again later.'),
                    ]
                )
            ),
        ]
    )]
    public function login(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        // Check if user account is active
        if ($user->status === 'suspended') {
            return $this->errorResponse('Your account has been suspended. Please contact support.', 403);
        }

        if ($user->status === 'inactive') {
            return $this->errorResponse('Your account is inactive. Please contact support.', 403);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return success response
        return $this->successResponse(
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'kyc_status' => $user->kyc_status,
                    'kyc_document_url' => $user->kyc_document_url,
                    'profile_picture_url' => $user->profile_picture_url,
                    'wallet_balance' => $user->wallet_balance,
                    'status' => $user->status,
                ],
                'token' => $token,
            ],
            'Login successful'
        );
    }

    /**
     * Admin login.
     */
    #[OA\Post(
        path: '/api/v1/auth/admin/login',
        summary: 'Admin login',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@ajo.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Admin login successful'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Admin User'),
                                        new OA\Property(property: 'email', type: 'string', example: 'admin@ajo.test'),
                                        new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Not an admin user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. Admin privileges required.'),
                    ]
                )
            ),
        ]
    )]
    public function adminLogin(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->toArray());
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        // Check if user is an admin
        if ($user->role !== 'admin') {
            return $this->errorResponse('Access denied. Admin privileges required.', 403);
        }

        // Check if user account is active
        if ($user->status !== 'active') {
            return $this->errorResponse('Your account is not active. Please contact support.', 403);
        }

        // Generate Sanctum token with admin abilities
        $token = $user->createToken('admin_auth_token', ['admin'])->plainTextToken;

        // Return success response
        return $this->successResponse(
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'token' => $token,
            ],
            'Admin login successful'
        );
    }
}
