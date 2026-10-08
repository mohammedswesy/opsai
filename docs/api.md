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

Financial and operational endpoints are not published yet.
