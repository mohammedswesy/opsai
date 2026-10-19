# WhatsApp

WhatsApp is a channel into the existing assistant. It does not have a separate AI stack. Inbound messages are verified, stored, and answered with the same tool registry, policies, and confirmation rules used by the staff assistant. Customer conversations only receive the customer tool set.

Automated tests use `FakeWhatsAppProvider`. A live Meta account has not been verified in this project.

## Meta setup

1. Create a Meta app and add the WhatsApp product.
2. Add a WhatsApp Business phone number and copy the phone number ID and business account ID.
3. Create a permanent access token for the system user that can send messages. Store it only on the server.
4. Set the callback URL to `https://your-domain.example/webhooks/whatsapp`.
5. Set the verify token to the same value as `WHATSAPP_VERIFY_TOKEN`.
6. Subscribe to the `messages` field.
7. Copy the app secret into `WHATSAPP_APP_SECRET`. Meta signs every webhook with it.

Message templates must be approved in Meta before they can be sent. If a template name or the connection is missing, the application returns an integration error and does not mark the message as sent.

## Environment

```
WHATSAPP_PROVIDER=fake
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_VERIFY_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_API_VERSION=v21.0
WHATSAPP_OUTBOUND_PER_MINUTE=30
WHATSAPP_REPLAY_WINDOW_SECONDS=900
```

`WHATSAPP_PROVIDER=fake` is the local and test default. Production must set `meta`. The application does not fall back to the fake provider in production. Per-organization tokens are encrypted on the `integrations` row. The React app receives only a masked hint.

`WHATSAPP_TOKEN` is still accepted as a fallback for `WHATSAPP_ACCESS_TOKEN`.

## Webhooks

`GET /webhooks/whatsapp` answers Meta's verification challenge.

`POST /webhooks/whatsapp` checks `X-Hub-Signature-256` before reading the body. The organization is chosen from the active integration whose phone number ID matches the payload. A customer-supplied organization id is ignored. Events are stored in `whatsapp_webhook_events` and processed by `ProcessWhatsAppEvent`. A repeated event id is acknowledged and not processed twice. Timestamps outside `WHATSAPP_REPLAY_WINDOW_SECONDS` are ignored.

The webhook returns after the event is queued. Tests and the Playwright server run the queue synchronously.

## Local development

Without Meta credentials, use the fake provider. The inbox shows a simulate form that posts a signed payload through the same webhook code. Delivery status stays at `sent` until a status webhook arrives. HTTP success from Meta means the message was accepted, not that the customer read it.

## Customer channel

The sender's phone number is normalized with the organization country and matched only inside that organization. An unknown number is not turned into a customer until a name is collected. The Arabic greeting `السلام عليكم` receives `وعليكم السلام 👋 كيف يمكننا مساعدتك؟`.

Customer tools are `create_service_request`, `get_customer_service_request`, `get_customer_appointment`, `get_basic_service_information`, `get_available_slot`, and `request_appointment`. The assistant asks only for a missing detail, and it quotes appointments, request status, availability, and catalog prices from those tools. Writes still wait for the customer's confirmation, including `نعم`. Invoice, payment, employee, and internal task tools are not offered. A request to speak to a person, including `أبغى موظف` and `أريد التحدث مع شخص`, sets the conversation to `human_required` and stops automatic replies until a staff member resumes it.
