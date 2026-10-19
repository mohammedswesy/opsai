# API

The browser application uses Inertia routes in `routes/web.php`, not a public JSON API.

The only HTTP API route is:

`GET /api/user`

It requires `Authorization: Bearer {sanctum token}` and returns the authenticated user's id, name, and email. It does not accept an organization id from the client and does not return tenant data.

Session-authenticated pages:

- `GET /login`, `POST /login`
- `GET /register`, `POST /register`
- `GET /forgot-password`, `POST /forgot-password`
- `GET /reset-password/{token}`, `POST /reset-password`
- `POST /logout`
- `POST /locale`
- `GET /organizations`, `POST /organizations/switch`
- `GET /`, `GET /organization`, `PUT /organization`
- `GET|POST /branches`, `GET|PUT|DELETE /branches/{branch}`
- `GET|POST /users`, `PUT|DELETE /users/{user}`

Operational pages, all inside the authenticated organization middleware:

- `customers`, `services`, `technicians`
- `requests`, `appointments`, `work-orders`, `POST /work-orders/{work_order}/transition`
- `tasks`
- `invoices`, `POST /invoices/{invoice}/issue`, `POST /invoices/{invoice}/cancel`
- `POST /work-orders/{work_order}/invoice`
- `payments`, `POST /payments/{payment}/refund`

These routes ignore a client-supplied organization id. Related customer, service, branch, technician, work order, and invoice ids must belong to the current organization. Invoice totals, payment status, and paid timestamps are calculated on the server.

`POST /api/ai/chat`, `POST /api/ai/actions/{action}/confirm`, and `POST /api/ai/actions/{action}/reject` use the signed-in session and the current organization. The browser never receives the model API key.

WhatsApp endpoints are not published yet. PDF download is not available yet.
