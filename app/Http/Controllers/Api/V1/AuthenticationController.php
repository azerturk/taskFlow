<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\CreatePersonalAccessTokenData;
use App\Http\Requests\Api\V1\CreatePersonalAccessTokenRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthenticationController
{
    public function __construct(private readonly AuthenticationService $authentication) {}

    public function issueToken(CreatePersonalAccessTokenRequest $request): JsonResponse
    {
        $token = $this->authentication->createPersonalAccessToken(
            CreatePersonalAccessTokenData::fromArray($request->validated()),
        );

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are invalid.',
            ]);
        }

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $token->accessToken->abilities,
        ]], 201);
    }

    public function me(Request $request): AuthenticatedUserResource
    {
        return new AuthenticatedUserResource($this->authentication->authenticatedUser($request->user()));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authentication->revokeCurrentToken($request->user(), $request->bearerToken());

        return response()->json(null, 204);
    }
}
