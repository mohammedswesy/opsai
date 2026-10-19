<?php

return [
    'provider' => env('AI_PROVIDER', 'fake'),
    'model' => env('AI_MODEL', env('OPENAI_MODEL', 'gpt-4.1-mini')),
    'api_key' => env('AI_API_KEY', env('OPENAI_API_KEY')),
    'timeout' => (int) env('AI_TIMEOUT', 20),
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 20),
    'max_input_length' => (int) env('AI_MAX_INPUT_LENGTH', 4000),
    'max_tool_calls' => (int) env('AI_MAX_TOOL_CALLS', 5),
];
