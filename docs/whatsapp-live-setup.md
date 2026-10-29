# WhatsApp live setup (Meta test number)

A step-by-step first live test of the WhatsApp Cloud API against a local Laravel app. For how the integration works, see [whatsapp.md](whatsapp.md). Automated tests never call Meta.

Never paste the access token, app secret or verify token into chat, tickets, screenshots or commits. `php artisan whatsapp:check` only prints yes/no.

## 1. Create the Meta app and get a test number

1. Go to [developers.facebook.com](https://developers.facebook.com/apps) → **Create app** → use case **Other** → type **Business**. Pick (or create) a business portfolio.
2. In the app dashboard, add the **WhatsApp** product. Meta creates a WhatsApp Business Account and a **test phone number** for you.
3. Open **WhatsApp → API Setup**. Note, without sharing them:
   - **Phone number ID** (under the test number, *not* the phone number itself)
   - **WhatsApp Business Account ID**
   - **Temporary access token** (valid about 24 hours; see section 7)
4. Open **App settings → Basic** and reveal the **App secret**. This signs every webhook (`X-Hub-Signature-256`).

## 2. Configure OpsAI

In `.env` (or the WhatsApp settings page for the secrets marked *settings*):

| Setting | Where | Notes |
| --- | --- | --- |
| `WHATSAPP_PROVIDER=meta` | `.env` | Default is `fake`, which never calls Meta. |
| `WHATSAPP_APP_SECRET` | `.env` or *settings* | From App settings → Basic. |
| `WHATSAPP_VERIFY_TOKEN` | `.env` or *settings* | Any long random string you choose; you type the same string into Meta. |
| `WHATSAPP_ACCESS_TOKEN` | `.env` or *settings* | The settings page value wins for that organization. |
| Phone number ID | *settings* | Required: incoming webhooks are routed to an organization by this ID. |
| WhatsApp Business Account ID | *settings* | Optional; lets `whatsapp:check --live` confirm the app subscription. |
| `WHATSAPP_API_VERSION` | `.env` | Optional, default `v21.0`. |
| `QUEUE_CONNECTION` | `.env` | With `database`, keep `php artisan queue:work` running, or nothing is answered. |

Then:

1. `php artisan config:clear`
2. Sign in as the organization owner → **WhatsApp → Settings**. Enter the phone number ID, business account ID, access token, app secret and verify token, set status to **Connected**, and save. Secrets are stored encrypted with `APP_KEY`; if you later change `APP_KEY`, save them again.
3. Run the readiness check (no Meta calls):

   ```
   php artisan whatsapp:check
   ```

   It lists each setting as yes/no, prints the webhook path, and ends with `Result: ready for a live test.` or a **Missing** list.

## 3. Expose local Laravel over HTTPS

Meta only calls public `https` URLs. Start the app (`php artisan serve --port=8000`), then use one of:

- **cloudflared** (no account needed for a quick tunnel):

  ```
  cloudflared tunnel --url http://localhost:8000
  ```

  Copy the `https://<random>.trycloudflare.com` URL it prints.
- **ngrok**:

  ```
  ngrok http 8000
  ```

  Copy the `https://<random>.ngrok-free.app` forwarding URL.

The quick-tunnel URL changes every time you restart it; update the Meta callback URL when it does. Setting `APP_URL` to the tunnel URL is optional (it only changes the URL `whatsapp:check` prints).

## 4. Register the webhook

1. Meta app → **WhatsApp → Configuration → Webhook → Edit**.
2. **Callback URL**: `https://<your-tunnel>/webhooks/whatsapp`
3. **Verify token**: the same string as `WHATSAPP_VERIFY_TOKEN` (or the one saved in settings).
4. Click **Verify and save**. Meta sends `GET /webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=…&hub.challenge=…`; OpsAI answers with the challenge as plain text. A 403 means the token does not match.

## 5. Subscribe to messages

1. Still under **Webhook fields**, click **Manage** and **Subscribe** to `messages`. Without it Meta sends nothing.
2. The **Test** button there sends a sample with a fake phone number ID and an old timestamp; OpsAI answers 200 and ignores it. That is expected.
3. Optional: `php artisan whatsapp:check --live` calls Meta once with the saved token to confirm the phone number is reachable and that the app is subscribed to the business account.

## 6. Add a test recipient and send the first messages

1. **WhatsApp → API Setup → To**: **Manage phone number list** → add your own WhatsApp number and enter the code Meta sends to it. A test number can only message up to five verified recipients.
2. In OpsAI, make sure a customer exists with that phone number (local `05…` or international `+9665…` both match), or the assistant will first ask for the sender's name.
3. From your phone, send a WhatsApp message **to the test number** first, for example "The air conditioner is not cooling." This opens the 24-hour window (section 7).
4. Watch:
   - `storage/logs/laravel.log` for `whatsapp.*` entries (no secrets are logged)
   - **WhatsApp** inbox in OpsAI: the inbound message and the reply
   - your phone: the reply, then the ticks as `sent`, `delivered` and `read` statuses arrive
5. Also try: reply "yes" to a proposed service request, send a photo or a voice note (stored for staff; the customer is asked to type), and "I want to talk to a person" (automatic replies stop).

## 7. Rules that catch first live tests

**The 24-hour window.** A business can send free-form (non-template) messages only within 24 hours of the customer's last message. OpsAI replies to customer messages, so its replies are inside the window. Once the window has closed, the inbox no longer offers free text: it explains why and shows a picker of approved templates (section 9). Anything else sent after 24 hours fails with Meta error `131047` (recorded on the message as `outside_24_hour_window`).

**Token expiry.** The token on the API Setup page is temporary and expires after about 24 hours (error `190`, recorded as `access_token_invalid`). For anything longer than a quick test:

1. Business Settings → **Users → System users** → add a system user (Admin).
2. **Add assets** → your app and your WhatsApp account, with full control.
3. **Generate new token** for the app with `whatsapp_business_messaging` and `whatsapp_business_management`, and choose **Never** for expiry.
4. Save it in OpsAI settings (or `WHATSAPP_ACCESS_TOKEN`) and run `php artisan whatsapp:check --live`. The **Token lifetime** line says `long-lived (system user token, does not expire)` or `expiring (..., expires <date> UTC)`; if Meta does not answer for this token it says `could not be determined`. The token itself is never printed.

Rotate the token if it is ever exposed.

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Meta says "The callback URL or verify token couldn't be validated" | Tunnel down, wrong path, or token mismatch (OpsAI answers 403) | Open `https://<tunnel>/webhooks/whatsapp` in a browser (403 is fine: it is reachable); retype the verify token; `config:clear` |
| Nothing arrives when you message the test number | `messages` field not subscribed, or app not subscribed to the account | Section 5; `whatsapp:check --live` |
| Log shows `whatsapp.webhook.signature_failed`, Meta gets 401 | Wrong app secret, or a proxy changed the body | Copy the app secret again from App settings → Basic; do not use a tunnel that rewrites bodies |
| Event stored with status `ignored`, error `unknown_phone_number_id` | Phone number ID in settings differs from the test number's ID, or status is not Connected | Copy the phone number ID (not the phone number) from API Setup; set Connected |
| Event `ignored`, error `stale_message` | The message was older than `WHATSAPP_REPLAY_WINDOW_SECONDS` (default 900) when it arrived, or the server clock is wrong | Check the system clock; resend the message |
| Inbound message stored but no reply | Queue worker not running | `php artisan queue:work` (or `QUEUE_CONNECTION=sync` for a local test) |
| Outbound message `failed`, reason `recipient_not_allowed` (`131030`) | Your number is not in the test recipient list | Section 6, step 1 |
| Outbound `failed`, reason `access_token_invalid` (`190`) | Temporary token expired or wrong | Section 7, token expiry |
| Outbound `failed`, reason `outside_24_hour_window` (`131047`) | More than 24 hours since the customer's last message | Send an approved template from the inbox (section 9), or wait for the customer to write |
| Red **WhatsApp needs attention** banner | Meta rejected the access token (`190`/HTTP 401) or a permission | Section 10 |
| `whatsapp:templates:sync --live` says `Not found in Meta` | The template name or language differs from the registry | Use exactly the names in section 9 and the language **Arabic** / **English** (not English (US)) |
| Reminder not sent, log `whatsapp.reminder_skipped` | See the `reason`: `no_opt_in`, `template_not_approved`, `no_phone`, `missing_parameter` | Section 9, *Reminders* |
| Outbound `failed`, reason `invalid_parameter` (`100`) | Wrong phone number ID or recipient format | Check the phone number ID; recipients are sent as digits with country code |
| Outbound `failed`, reason `rate_limited` | Too many messages to one user or overall | Wait; the per-organization limit is `WHATSAPP_OUTBOUND_PER_MINUTE` |
| Reply says "I can only read text messages for now" | Customer sent audio, a location, a sticker or a photo without a caption | Expected; staff see the media details in the inbox |
| No automatic replies for a customer | Conversation is in human handoff | Use **Resume assistant** in the inbox |
| `whatsapp:check` says saved credentials are not readable | `APP_KEY` changed after saving | Save the WhatsApp settings again |

Logged Meta failures include the HTTP status, Meta `error_code`, `error_subcode`, `error_type` and `fbtrace_id` (quote the trace id to Meta support), never the token or Meta's error text.

## 9. Message templates and appointment reminders

Outside the 24-hour window WhatsApp only delivers **approved message templates**. OpsAI uses two, each in Arabic and English:

| Purpose | Template name | Languages |
| --- | --- | --- |
| Appointment reminder | `opsai_appointment_reminder` | Arabic (`ar`), English (`en`) |
| Appointment confirmation | `opsai_appointment_confirmation` | Arabic (`ar`), English (`en`) |

The names can be changed with `WHATSAPP_TEMPLATE_REMINDER` and `WHATSAPP_TEMPLATE_CONFIRMATION`, but then the Meta names must match.

### Create them in WhatsApp Manager

1. [business.facebook.com](https://business.facebook.com) → **WhatsApp Manager** → **Account tools → Message templates** → **Create template**.
2. **Category: Utility** (appointment reminders and confirmations are transactional; do not choose Marketing). Type: **Default**.
3. **Name**: as in the table above (lower case, underscores). **Language**: add **Arabic** and **English**. Choose plain *English*, not *English (US)*, so the code is `en`.
4. **Variable type: Number**. Paste the body below for each language. The variables must stay in this order; OpsAI fills them as:
   `{{1}}` customer first name · `{{2}}` service · `{{3}}` appointment date · `{{4}}` appointment time (24-hour, Riyadh time) · `{{5}}` company name.
5. Fill the sample values Meta asks for (for example `Sara`, `AC cleaning`, `Nov 2, 2026`, `08:30`, `Riyadh Cooling`), no header, no buttons, then **Submit**.

**opsai_appointment_reminder**

- English: `Hello {{1}}, this is a reminder of your {{2}} appointment on {{3}} at {{4}} with {{5}}. Reply to this message if you need to change it.`
- Arabic: `مرحباً {{1}}، نذكّرك بموعد {{2}} يوم {{3}} الساعة {{4}} مع {{5}}. إذا احتجت إلى تغيير الموعد، رد على هذه الرسالة.`

**opsai_appointment_confirmation**

- English: `Hello {{1}}, your {{2}} appointment is confirmed for {{3}} at {{4}} with {{5}}. Reply to this message if you have any questions.`
- Arabic: `مرحباً {{1}}، تم تأكيد موعد {{2}} يوم {{3}} الساعة {{4}} مع {{5}}. لأي استفسار، رد على هذه الرسالة.`

Meta rejects a body that starts or ends with a variable, so keep the text around them. Dates are written in the template's language with Latin digits (`Nov 2, 2026`, `2 نوفمبر 2026`), like the rest of OpsAI.

**Approval time.** Utility templates are usually reviewed within minutes; Meta allows up to 24 hours (occasionally 48). A rejected template can be edited and resubmitted. A template Meta *pauses* or *disables* later shows as **Unknown** in OpsAI and is not sent.

### Sync the statuses into OpsAI

The WhatsApp Business Account ID must be saved in **WhatsApp → Settings**. Then:

```
php artisan whatsapp:templates:sync          # lists the registry, no Meta call
php artisan whatsapp:templates:sync --live   # registers the four templates and reads their statuses from Meta
```

`--live` prints templates that are missing in Meta and Meta templates OpsAI does not use. An owner or admin can do the same with **Sync statuses from Meta** on the settings page, where the templates are listed read-only with their status (Approved, In review, Rejected, Unknown). Run it again after Meta approves them.

### Sending a template from the inbox

When the customer last wrote more than 24 hours ago (or never wrote), the conversation shows *The 24-hour reply window is closed* instead of the text box, with a picker of **approved** templates and the customer's upcoming appointments. Staff who may reply in the inbox (owner, admin, manager, dispatcher, customer service) can send one. Each template is sent at most once per appointment; a second send returns the first message. Sends count towards `WHATSAPP_OUTBOUND_PER_MINUTE`.

### Reminders

1. Create and approve `opsai_appointment_reminder` (above) and sync.
2. **Opt-in rule:** a reminder is only ever sent to a customer whose **Agrees to WhatsApp messages** box is ticked on the customer form (stored as the time consent was recorded). Tick it only when the customer has agreed. Unticking it stops reminders immediately.
3. An owner or admin turns on **Send WhatsApp reminders** in **WhatsApp → Settings**.
4. Make sure the scheduler runs: `php artisan schedule:work` locally, or on a server the cron entry `* * * * * php artisan schedule:run`. `whatsapp:send-reminders` runs hourly and sends the reminder to scheduled or confirmed appointments that start between 1 and 24 hours from now (`WHATSAPP_REMINDER_HOURS_BEFORE`), once per appointment.

Appointments are skipped, with a `whatsapp.reminder_skipped` log entry holding only the appointment id and a reason, when the customer did not opt in (`no_opt_in`), the template is not approved (`template_not_approved`), there is no usable phone number (`no_phone`), or the appointment has no service to name (`missing_parameter`). Organizations with reminders off log `whatsapp.reminders_skipped` with `reminders_disabled`. No names or phone numbers are logged.

## 10. Token health (the "needs attention" banner)

When Meta rejects a send, a template sync or a connection check because of the access token (error `190`, HTTP 401) or a missing permission (errors `10`/`200`, HTTP 403), OpsAI marks the organization's WhatsApp connection as **needs attention**: it stores only a reason code (`access_token_invalid` or `permission_denied`) and the time, never Meta's error text. A red banner then appears on the WhatsApp inbox, every conversation and the settings page, in Arabic and English, with a link to these instructions.

To fix it: generate a new token (section 7, preferably a system user token that never expires), save it in **WhatsApp → Settings**, then click **Verify connection** or run `php artisan whatsapp:check --live`. The banner clears automatically after the next successful send, a successful verify, or a successful `whatsapp:check --live`.

Local testing with `WHATSAPP_PROVIDER=fake`: saving the access token `fake-invalid-token` makes the fake provider behave like a rejected token, so the banner can be seen without Meta.
