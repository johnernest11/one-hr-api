<?php

namespace App\Services\Authentication;

use App\Interfaces\Authentication\AuthTokenManager;
use App\Models\User;
use JWT;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Log;
use STS\JWT\Exceptions\InvalidAudience;
use STS\JWT\Exceptions\InvalidID;
use STS\JWT\Exceptions\InvalidSignature;
use STS\JWT\Exceptions\TokenExpired;
use STS\JWT\Exceptions\ValidationException;

class JWTAuthService implements AuthTokenManager
{
    public string $jwtId;

    public function __construct()
    {
        $this->jwtId = config('auth.jwt.id');
    }

    /** {@inheritDoc} */
    public function generate(User $user): string
    {
        return JWT::get(
            $this->jwtId,
            ['user_id' => $user->id],
            now()->addSeconds(config('auth.jwt.lifetime_seconds')),
            config('auth.jwt.signing_key')
        );
    }

    /** {@inheritDoc} */
    public function isValid(string $token): bool
    {
        try {
            $parsedToken = JWT::parse($token);
        } catch (CannotDecodeContent $error) {
            Log::warning('JWT token is malformed: '.$error::class);

            return false;
        }

        try {
            $parsedToken->validate($this->jwtId);
        } catch (InvalidSignature|TokenExpired|InvalidAudience|InvalidID|ValidationException $error) {
            // We log if we get any other error besides the token expiring
            if (! ($error instanceof TokenExpired)) {
                Log::warning('A JWT Exception has occurred: '.$error::class);
            }

            return false;
        }

        return true;
    }
}
