<?php

namespace App\AI\Support;

/**
 * What the user sees for each assistant failure. Keys are the English text and are
 * translated through lang/ar.json. Provider details never reach this message; they go
 * to the log (without secrets) where the failure is raised.
 */
final class AIErrorMessages
{
    private const MESSAGES = [
        'not_configured' => 'The assistant is not set up yet. Ask an administrator to add the AI API key.',
        'invalid_api_key' => 'The assistant cannot sign in to the AI service. Ask an administrator to check the AI API key.',
        'insufficient_quota' => 'The AI service account has run out of credit. Ask an administrator to check the billing.',
        'rate_limited' => 'The AI service is getting too many requests. Wait a minute and try again.',
        'too_many_messages' => 'You are sending messages too quickly. Wait a minute and try again.',
        'timeout' => 'The assistant took too long to answer. Try again, or ask a shorter question.',
        'unreachable' => 'The assistant cannot reach the AI service right now. Try again in a few minutes.',
        'unavailable' => 'The AI service is unavailable right now. Try again later.',
        'malformed_response' => 'The assistant sent an answer it could not read. Try again.',
    ];

    /** Reasons raised in code that share a message with another reason. */
    private const ALIASES = [
        'missing_api_key' => 'not_configured',
        'unsupported_provider' => 'not_configured',
        'malformed_tool_call' => 'malformed_response',
        'http_error' => 'unavailable',
    ];

    public static function key(string $reason): string
    {
        $reason = self::ALIASES[$reason] ?? $reason;

        return array_key_exists($reason, self::MESSAGES) ? $reason : 'unavailable';
    }

    /** The English text, used as the exception's public message and the translation key. */
    public static function english(string $reason): string
    {
        return self::MESSAGES[self::key($reason)];
    }

    public static function for(string $reason, ?string $locale = null): string
    {
        return __(self::english($reason), [], $locale);
    }
}
