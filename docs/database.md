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

Customers, requests, work orders, invoices, AI conversations, and the other domain tables are not migrated yet.
