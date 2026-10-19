<?php

namespace App\AI\Contracts;

use App\AI\Data\AIMessage;
use App\AI\Data\AIResponse;

interface AIProviderInterface
{
    /**
     * @param  list<AIMessage>  $messages
     * @param  list<array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools): AIResponse;
}
