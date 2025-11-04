<?php

namespace App\Services\Authentication\Interfaces;

interface PersistentAuthTokenManager extends AuthTokenManager, CanInvalidateAuthTokens, CanRetrieveAuthTokens
{
}
