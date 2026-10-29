<?php

namespace App\WhatsApp;

/**
 * Names the WhatsApp Cloud API error codes a first live test runs into. Used for send
 * failures and for "failed" delivery statuses, so the inbox shows the same reason.
 */
final class MetaErrorCodes
{
    public static function reason(?int $code, ?int $httpStatus = null): string
    {
        return match (true) {
            $code === 190 || $httpStatus === 401 => 'access_token_invalid',
            $code === 131047 => 'outside_24_hour_window',
            $code === 131030 => 'recipient_not_allowed',
            $code === 131026 => 'undeliverable',
            in_array($code, [4, 80007, 130429, 131048, 131056], true) => 'rate_limited',
            $code === 100 => 'invalid_parameter',
            in_array($code, [10, 200], true) || $httpStatus === 403 => 'permission_denied',
            default => 'http_error',
        };
    }
}
