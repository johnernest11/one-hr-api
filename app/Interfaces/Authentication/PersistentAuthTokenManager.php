<?php

namespace App\Interfaces\Authentication;

interface PersistentAuthTokenManager extends AuthTokenManager, CanInvalidateAuthTokens, CanRetrieveAuthTokens
{
}
