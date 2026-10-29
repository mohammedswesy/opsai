<?php

namespace App\AI\Support;

/**
 * The one place that decides which language the assistant answers in, and the
 * system-prompt rule that tells the model. The reply follows the user's latest
 * message; when that message has no letters to go by (a number, an emoji), it falls
 * back to the user's interface locale.
 */
final class ReplyLanguage
{
    private const NAMES = ['ar' => 'Arabic', 'en' => 'English'];

    private function __construct(
        public readonly string $code,
        public readonly bool $fromMessage,
    ) {}

    public static function resolve(?string $latestUserMessage, ?string $fallbackLocale): self
    {
        $text = $latestUserMessage ?? '';
        // Letters only: the Arabic script block also holds "؟", "،" and Arabic-Indic digits.
        $arabic = preg_match_all('/(?=\p{L})\p{Arabic}/u', $text);
        $latin = preg_match_all('/[A-Za-z]/', $text);

        if ($arabic > 0 || $latin > 0) {
            return new self($arabic >= $latin ? 'ar' : 'en', true);
        }

        return new self(array_key_exists((string) $fallbackLocale, self::NAMES) ? (string) $fallbackLocale : 'en', false);
    }

    /** The system-prompt rule. */
    public function rule(): string
    {
        $name = self::NAMES[$this->code];
        $source = $this->fromMessage
            ? "The user's latest message is in {$name}."
            : "The user's latest message has no clear language, so use their interface language, {$name}.";

        return <<<RULE
Reply language: {$name}. {$source}
Always reply in the language of the user's latest message, even if earlier messages, tool results, or record names are in another language. If the user switches language, switch with them.
Keep names, phone numbers, references, dates, and amounts exactly as the tools return them; do not translate or transliterate them.
RULE;
    }
}
