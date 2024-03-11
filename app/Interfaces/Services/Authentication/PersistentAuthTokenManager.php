<?php

namespace App\Interfaces\Services\Authentication;

interface PersistentAuthTokenManager extends AuthTokenManager, CanInvalidateAuthTokens, CanRetrieveAuthTokens
{
}
