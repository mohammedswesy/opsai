# AI live check

A manual check of the live OpenAI assistant before a demo or release. Automated tests never call OpenAI; they use `FakeAIProvider` or `Http::fake()`.

## 1. Configure

Set these in `.env` (never commit them, never paste the key into chat or logs):

| Variable | Config key | Required | Default |
| --- | --- | --- | --- |
| `AI_PROVIDER` | `ai.provider` | yes, set to `openai` | `fake` |
| `AI_API_KEY` (or `OPENAI_API_KEY`) | `ai.api_key` | yes | none |
| `AI_MODEL` (or `OPENAI_MODEL`) | `ai.model` | no | `gpt-4.1-mini` |
| `AI_TIMEOUT` | `ai.timeout` (seconds) | no | `20` |
| `AI_RATE_LIMIT_PER_MINUTE` | `ai.rate_limit_per_minute` (per user) | no | `20` |
| `AI_MAX_INPUT_LENGTH` | `ai.max_input_length` | no | `4000` |
| `AI_MAX_TOOL_CALLS` | `ai.max_tool_calls` | no | `5` |

Then run `php artisan config:clear` so the new values are read.

## 2. Health check

```
php artisan ai:health-check
```

It runs only when `APP_ENV` is `local` or `testing`, and sends one tiny request ("Reply with the single word ok.") to the Responses API.

- **Success** prints `Provider: openai`, `Model: …`, `Latency: … ms` and `The assistant responded.`
- **Failure** prints `Reason: <code>` and a message. It never prints the key or the provider's error text.

| Reason | Meaning | Fix |
| --- | --- | --- |
| `missing_api_key` | `AI_API_KEY` is empty | Set the key, then `config:clear` |
| `invalid_api_key` | OpenAI answered 401 | Create a new key in the OpenAI dashboard |
| `insufficient_quota` | 429 with `insufficient_quota` | Add credit or raise the project budget |
| `rate_limited` | 429 rate limit | Wait a minute; check the project's limits |
| `timeout` | No answer within `AI_TIMEOUT` | Retry; raise `AI_TIMEOUT` if it repeats |
| `unreachable` | DNS, TLS or network failure | Check the server's outbound HTTPS access |
| `unavailable` | Other OpenAI error (5xx) | Retry later |

Each failure is also logged as `ai.openai.http` or `ai.openai.connection`, with the status, OpenAI error type and code, and `x-request-id`, but never the key or the error message.

## 3. Ask the assistant

Sign in as `owner@riyadh-cooling.test` and open **Assistant**. Ask each question in a new conversation, once with the interface in English and once in Arabic. The reply must follow the language of the question, not the interface.

### English

1. **Customer:** "Find the customer North Wind Villa and show their phone number."
2. **Appointments:** "Which appointments are scheduled today?"
3. **Work orders:** "Show the last five work orders and their status."
4. **Technicians:** "Which technicians are available right now?"
5. **Write action:** "Create a task for Riyadh Technician to call North Wind Villa tomorrow."

### Arabic

1. **عميل:** "ابحث عن العميل North Wind Villa واعرض رقم جواله."
2. **مواعيد:** "ما هي مواعيد اليوم؟"
3. **أوامر العمل:** "اعرض آخر خمسة أوامر عمل وحالتها."
4. **الفنيون:** "مين الفنيين المتاحين الحين؟"
5. **إجراء كتابة:** "أنشئ مهمة لـ Riyadh Technician للاتصال بالعميل North Wind Villa بكرة."

### Correct behaviour

- **Language:** each answer is in the language of the question. Names, phone numbers, references and dates appear exactly as stored (an English customer name stays in English inside an Arabic answer).
- **Facts come from tools:** the "Checked: …" line under the thread names the read tool used (customer, today's appointments, recent work orders, available technicians). Counts, names and statuses match the matching screen. If nothing is found, the assistant says so and does not invent records.
- **Write action:** the assistant does not save anything yet. Its reply is "Please confirm this action before I save it." (Arabic: "يرجى تأكيد هذا الإجراء قبل أن أحفظه.") and a confirmation card shows the task details with "Nothing is saved until you confirm." Only **Confirm** creates the task (check **Tasks**); **Cancel** creates nothing.
- **Scope:** questions about invoices, payments, refunds or prices are declined; do not use them in this check.
- **Errors:** if something fails, the page shows one of the reasons above in the interface language, never "busy" for every case and never provider details.
