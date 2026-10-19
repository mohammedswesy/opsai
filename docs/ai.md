# AI assistant

The assistant answers questions about the signed-in organization by calling registered application tools. It does not query the database, run code, or call payment or WhatsApp providers.

Automated tests mock the OpenAI HTTP API. A live account is checked only with `php artisan ai:health-check` on a development machine that already has `AI_PROVIDER=openai` and a key. The last run exited with status 1 and the message `AI_PROVIDER must be openai` because this environment still uses the fake provider and has no API key. The live `/ai` prompts were not sent to OpenAI.

## Architecture

- `AIProviderInterface` is implemented by `OpenAIProvider` and `FakeAIProvider`.
- `AIConversationService` stores the conversation, calls the provider, and runs tools.
- `AIToolRegistry` is the only way a model can reach application data.
- `AIActionService` keeps write proposals pending until the same user confirms them.
- `AIContextService` sends the organization name, locale, currency, time, role, and selected branch name. It does not send business records.

`OpenAIProvider` calls `POST https://api.openai.com/v1/responses` with `store: false`. It does not use the Assistants API. The application keeps the transcript and runs tools locally.

## Configuration

`config/ai.php` reads `AI_PROVIDER`, `AI_API_KEY` (or `OPENAI_API_KEY`), `AI_MODEL`, `AI_TIMEOUT`, and `AI_MAX_TOOL_CALLS`. Leave `AI_PROVIDER=fake` for local development, Pest, and Playwright. Production does not use `FakeAIProvider`. If `AI_PROVIDER=openai` and the request fails, the user sees the provider error. The API key stays in server config and is not passed to React or written into logs.

## Health check

`php artisan ai:health-check` is a local development command. It checks that the provider is `openai` and the key is present, sends one short prompt with no tools, and prints the provider, model, and latency. It exits with a non-zero status when configuration or the provider fails, and it never prints the key.

## Live test

With a real key in the local environment, set `AI_PROVIDER=openai` and open `/ai` as the Riyadh owner.

- “How many open service requests do we have?” should answer from `get_open_service_requests`. The seeded organization has 1 open request.
- “Show me the overdue invoices.” should answer from `get_overdue_invoices`. The seeded data has 1 overdue invoice totaling SAR 1150.00.
- “Create a task for the technician.” should show a confirmation and create the task only after that confirmation. The action and an activity row are recorded then.

Restore `AI_PROVIDER=fake` before Playwright so the browser suite does not call OpenAI.

## Common failures

Missing or rejected keys say the assistant is not configured. Timeouts say it took too long. A response the application cannot parse says it could not be used. Invalid tool arguments and unauthorized tools are stored as failed or refused results. Too many tool calls stops with a short lookup message. None of these responses include a stack trace or the API key.

## Tools

Read tools use the existing operations and finance services. Write tools are `create_task`, `create_service_request`, `create_appointment`, `assign_technician`, and `create_work_order`. They call the same services as the screens and stay pending until confirmation.

There are no invoice, payment, or refund tools. `get_basic_service_information` can return the stored catalog price for a customer. The assistant must not invent a price that the tool did not return.

Each tool checks the current user's policy, ignores `organization_id` and `branch_id` in the model arguments, and scopes rows with the organization global scope. A selected branch comes from the user's session. `all_branches: true` is the only way a tool widens that selection, and only when the model sets that flag.

## Confirmation

A write tool creates an `ai_actions` row with status `pending` and an idempotency key. Confirm moves it through `approved` and `executing` to `executed` or `failed`. Confirming the same row again returns the stored result and does not create another record. Reject sets `rejected`.

## Limits

`AI_RATE_LIMIT_PER_MINUTE`, `AI_MAX_INPUT_LENGTH`, and `AI_MAX_TOOL_CALLS` are in `config/ai.php`. The assistant stops after the tool-call limit. Provider failures return a short message and are logged on the server without a stack trace.

## Tests

`tests/Feature/AiTest.php` uses `FakeAIProvider`. `tests/Feature/OpenAIProviderTest.php` uses `Http::fake` against `/v1/responses` for a missing key, an invalid key, a timeout, a malformed body, invalid tool arguments, an unauthorized tool, the tool-call limit, organization arguments sent by the model, and the health check. Playwright still uses the fake provider. No automated test calls a live OpenAI account.
