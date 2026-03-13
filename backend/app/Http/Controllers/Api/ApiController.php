<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Rotational Contribution App API',
    description: 'API documentation for the Ajo Platform - A digital rotational savings system',
    contact: new OA\Contact(
        name: 'API Support',
        email: 'support@ajo.example.com'
    )
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Development Server'
)]
#[OA\Server(
    url: 'https://api.ajo.example.com',
    description: 'Production Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your JWT token in the format: Bearer {token}'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication and authorization endpoints'
)]
#[OA\Tag(
    name: 'Users',
    description: 'User management and profile endpoints'
)]
#[OA\Tag(
    name: 'Groups',
    description: 'Contribution group management endpoints'
)]
#[OA\Tag(
    name: 'Contributions',
    description: 'Contribution tracking and payment endpoints'
)]
#[OA\Tag(
    name: 'Payouts',
    description: 'Payout management and scheduling endpoints'
)]
#[OA\Tag(
    name: 'Wallet',
    description: 'Wallet and transaction management endpoints'
)]
#[OA\Tag(
    name: 'Admin',
    description: 'Administrative endpoints'
)]
class ApiController extends Controller
{
    /**
     * Return a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Return an error response.
     *
     * @param string $message
     * @param int $code
     * @param array|null $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, int $code = 400, ?array $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response.
     *
     * @param array $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationErrorResponse(array $errors)
    {
        return $this->errorResponse('Validation failed', 422, $errors);
    }
}
