<?php

namespace App\Services\Authentication;

use App\Models\User;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use Carbon\Carbon;
use JWT;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Log;
use STS\JWT\Exceptions\InvalidAudience;
use STS\JWT\Exceptions\InvalidID;
use STS\JWT\Exceptions\InvalidSignature;
use STS\JWT\Exceptions\TokenExpired;
use STS\JWT\Exceptions\ValidationException;

class JwtAuthService implements AuthTokenManager
{
    private string $jwtId;

    private string $signingKey;

    public function __construct()
    {
        $this->jwtId = config('jwt.id');
        $this->signingKey = config('jwt.signing_key');
    }

    /** {@inheritDoc} */
    public function generateToken(User $user, Carbon $expiresAt, string $clientName = ''): string
    {
        return JWT::get(
            $this->jwtId,
            ['user_id' => $user->id, 'client_name' => $clientName],
            $expiresAt,
            $this->signingKey
        );
    }

    /** {@inheritDoc} */
    public function tokenIsValid(string $token): bool
    {
        try {
            $parsedToken = JWT::parse($token);
        } catch (CannotDecodeContent|InvalidTokenStructure $error) {
            Log::debug('JWT token is malformed: '.$error::class, ['method' => __METHOD__]);

            return false;
        }

        try {
            $parsedToken->validate($this->jwtId, $this->signingKey);
        } catch (InvalidSignature|TokenExpired|InvalidAudience|InvalidID|ValidationException $error) {
            Log::debug('A JWT Exception has occurred: '.$error::class, ['method' => __METHOD__]);

            return false;
        }

        return true;
    }

    public function getTokenOwner(string $token): ?User
    {
        if (! $this->tokenIsValid($token)) {
            return null;
        }

        $parsedToken = JWT::parse($token);
        $userId = $parsedToken->getPayload()['user_id'];

        return User::find($userId);
    }
}
