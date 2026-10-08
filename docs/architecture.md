# Architecture

OpsAI is a modular Laravel monolith. The web application is server-rendered through Inertia and React. There is no microservice split.

## Tenancy

An organization is the tenant. Users join an organization through `organization_user`, which stores a role string. The current organization id lives in the session under `current_organization_id`.

`EnsureCurrentOrganization` loads that membership, rejects non-members, and stores the organization plus role in `OrganizationContext`. Models that use `BelongsToOrganization` automatically filter queries to that organization. If no organization is active, those queries return no rows. This is the shared scoping mechanism. Branch controllers do not add their own organization filters.

Users are global accounts because one person can belong to more than one organization. Branch records are tenant data.

## Authorization

`OrganizationRole` is a PHP backed enum. Permissions are defined on the enum, not in the database. Policies check those permissions. The React app hides links from the same permission list, but the backend is the authority.

Owners can manage organization status and other owners. Admins can manage members except owners. Managers can manage branches and view the team. Other roles can view branches.

## Web and API

Browser pages use the `web` session guard. `GET /api/user` uses Sanctum bearer tokens. Business APIs are not added until the resources they expose exist.

## Locale

Guest pages can switch `en` and `ar` through the session. Inside an organization, the organization locale wins and sets document direction. Country, currency, and timezone are stored per organization and are not fixed to Saudi Arabia.

## External services

AI, WhatsApp, and payment credentials are environment variables only. No provider calls are made in this phase.
