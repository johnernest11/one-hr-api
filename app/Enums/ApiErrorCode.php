<?php

namespace App\Enums;

/*
|--------------------------------------------------------------------------
| ApiErrorCode
|--------------------------------------------------------------------------
|
| Provides a list of error codes we can send back to the client
|
*/

enum ApiErrorCode: string
{
    case VALIDATION = 'VALIDATION_ERROR';
    case RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND_ERROR';
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS_ERROR';
    case SMTP_ERROR = 'SMTP_ERROR';
    case UNAUTHORIZED = 'UNAUTHORIZED_ERROR';
    case FORBIDDEN = 'FORBIDDEN_ERROR';
    case UNKNOWN_ROUTE = 'UNKNOWN_ROUTE_ERROR';
    case RATE_LIMIT = 'TOO_MANY_REQUESTS_ERROR';
    case DEPENDENCY_ERROR = 'DEPENDENCY_ERROR';
    case SERVER = 'SERVER_ERROR';
    case INCORRECT_OLD_PASSWORD = 'INCORRECT_OLD_PASSWORD_ERROR';
    case PAYLOAD_TOO_LARGE = 'PAYLOAD_TOO_LARGE_ERROR';
    case EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED_ERROR';
    case BAD_REQUEST = 'BAD_REQUEST_ERROR';
    case WEBHOOKS_DISABLED = 'WEBHOOKS_DISABLED';
    case INVALID_MFA_ATTEMPT_TOKEN = 'INVALID_MFA_ATTEMPT_TOKEN_ERROR';
    case INVALID_MFA_CODE = 'INVALID_MFA_CODE_ERROR';
    case INVALID_MFA_BACKUP_CODE = 'INVALID_MFA_BACKUP_CODE_ERROR';
}
