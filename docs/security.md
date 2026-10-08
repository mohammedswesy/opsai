# Security

What is enforced now:

- Session authentication for the web app, with session regeneration on login and invalidation on logout
- Sanctum bearer tokens for `GET /api/user`
- CSRF protection on web POST, PUT, and DELETE
- Login, registration, and password reset routes limited to 5 requests per minute per IP outside tests
- Form request validation for registration, organization settings, branches, and members
- Mass assignment limited to model `$fillable` lists
- Policies for organization updates, branch changes, and team management
- Tenant scoping on branches. A branch id from another organization returns 404
- An organization must keep one owner. Admins cannot assign or edit the owner role
- Suspended organizations are blocked for members who cannot update the organization
- Password reset always returns the same message, whether or not the email exists
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- Secrets stay in environment variables. OpenAI, WhatsApp, and payment values are not shared through Inertia

Not built yet: file upload rules, webhook signature checks, signed URLs, audit logs, and payment verification. Those land with the features that need them.
