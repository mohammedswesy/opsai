# Database

Identifiers for users, organizations, and branches are ULIDs. Status and role values are strings backed by PHP enums. The schema does not use database enums.

## Tables in this phase

`users`

Account identity: name, email, password. Email is globally unique.

`organizations`

Tenant profile: name, unique slug, legal name, tax number, phone, email, country, timezone, currency, locale, logo path, status, JSON settings. Soft deleted.

`organization_user`

Membership. Composite primary key of organization and user. `role` is one of `owner`, `admin`, `manager`, `dispatcher`, `technician`, `accountant`, `customer_service`.

`branches`

Tenant locations. Unique `(organization_id, code)`. Includes address, city, country, coordinates, phone, timezone, and status. Soft deleted.

`branch_user`

Which members are associated with a branch. Composite primary key of branch and user.

`personal_access_tokens`

Sanctum tokens. The tokenable morph uses a ULID id.

Laravel also owns `sessions`, `password_reset_tokens`, `cache`, and `jobs`.

## Indexes and keys

- Unique organization slug
- Unique branch code inside an organization
- Foreign keys from branches and memberships back to organizations and users, with cascade deletes
- Indexes on organization status, branch status, and membership role

## Assistant tables

`ai_conversations`, `ai_messages`, and `ai_actions` store organization-scoped chats and confirmed writes. Messages keep tool calls and results as JSON. Actions store the idempotency key, approval time, and execution time.

## Finance tables

`invoice_sequences` stores the last number for an organization and year. The unique key is `(organization_id, year)`.

`invoices` stores the organization, optional branch, customer, optional work order, server-generated invoice number, status, dates, currency, and calculated money columns. Unique `(organization_id, invoice_number)`. Soft deleted.

`invoice_items` stores description, quantity, unit price, tax rate, and calculated line amounts.

`payments` stores a manual payment against an invoice in the same organization: amount, currency, method, status, reference, paid at, and created by.

## Operations tables

`customers`, `services`, and `technicians` are tenant-owned and soft deleted. `technician_skills` stores a skill name and an optional catalog service. `service_requests`, `appointments`, `work_orders`, `tasks`, and `activities` are tenant-owned and are not soft deleted.

Appointments reference a work order through a foreign key added after `work_orders` exists. Work-order status changes are validated in application code. Appointment overlap is rejected for the same technician unless the appointment is cancelled.

Invoices, payments, AI conversations, and WhatsApp messages are not migrated yet.
