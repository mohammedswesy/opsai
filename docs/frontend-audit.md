# OpsAI frontend audit

Audit date: 2026-10-24 · Branch: `main` @ `6fefed8` · Scope: `resources/js`, `resources/css/app.css`, `resources/views/app.blade.php`, plus the controllers and shared props that feed the pages.

This is an audit only. No source file was changed. The plan at the end is a proposal; nothing in it has been started.

**Stack:** Laravel + Inertia v3 (React 19, TypeScript, `strictMode`), Tailwind CSS v4 through `@tailwindcss/vite`, lucide-react icons, axios. There is **no `tailwind.config.*`**: all theme configuration is in `resources/css/app.css` (`@theme`). There are no `hooks/` or `utils/` folders. Hooks live inside `components/` (for example `useDocumentLocale` and `useVisitState`), and the only util is `lib/labels.ts`.

---

## Summary

- **The design system is mostly hidden overrides, not real tokens.** `app.css` redefines Tailwind's `zinc` and `teal` scales so that "teal" renders as blue. It also styles buttons, inputs and cards through compound selectors such as `button.rounded-md.bg-teal-800`. The semantic tokens it defines (`brand`, `ink`, `line`, `canvas`) are used **zero** times.
- **Two brand colour families are mixed together.** `teal-*` is used 50 times in 21 files and resolves to blue. Raw `blue-*` is used 19 times in 5 files, alongside indigo, violet, emerald and cyan. The shell, dashboard and command palette use `blue-*`. Most pages and Dispatch use `teal-*`.
- **About half the screens show raw backend values.** These include ISO datetimes (16 `.slice()` calls in 7 files, plus fields printed unformatted), raw role and status keys, raw activity action keys, money printed as `123.00 SAR`, and detail cards whose values have no labels.
- **Arabic support is incomplete outside the React bundle.** There is no `lang/` directory. As a result, all 34 flash messages (`__('…')`), every validation error and the pagination labels ("« Previous", "Next »") always appear in English, even in Arabic mode.
- **The RTL basics are good.** There are zero physical `ml/mr/pl/pr/left/right` utilities, 56 logical ones, `dir` is set on `<html>`, and the font switches for Arabic. The RTL weaknesses are in specific screens (see §6), not in the foundation.
- **Accessibility gaps are concentrated in overlays and forms.** No dialog or drawer traps focus. `ConfirmDialog` and the Dispatch drawer don't close on Escape. Form errors are not linked to their inputs. The focus ring is a 40%-opacity blue outline.
- **Dashboard and Dispatch are well built. The detail ("Show") pages and settings pages are the weakest.** These are Work order, Request, Customer, Technician and Payment, plus WhatsApp settings and Users. Each is an unlabeled two-column grid of `<p>` tags.

---

## 1. Structure

```
resources/
├── css/app.css                 Tailwind v4 entry: @theme tokens, palette overrides, global element styles
├── views/app.blade.php         Root HTML: Bunny fonts (Inter + IBM Plex Sans Arabic), Vite, Inertia mount
└── js/
    ├── app.tsx                 createInertiaApp; title template "X - OpsAI"; pages auto-resolved from ./pages
    ├── bootstrap.ts            window.axios with X-Requested-With header
    ├── i18n/
    │   ├── index.ts            useI18n(): picks ar/en dictionary from shared `locale`, returns { t, locale, dir }
    │   ├── en.ts               English dictionary (405 lines); exports `Messages` type
    │   └── ar.ts               Arabic dictionary typed as `Messages` (key parity enforced by TypeScript)
    ├── lib/
    │   └── labels.ts           optionLabel(labels, value): looks up t.labels[value], falls back to the raw value
    ├── types/
    │   ├── global.d.ts         window.axios typing
    │   ├── inertia.d.ts        Shared page props: name, locale, dir, auth.user, organization (+role), permissions, flash
    │   └── page.ts             Paginator<T> { data, links }, Option { id, name }
    ├── layouts/
    │   ├── AppLayout.tsx       Authenticated shell: sidebar (grouped, collapsible, permission-filtered), top bar,
    │   │                       Ctrl/⌘K palette, account menu, mobile drawer, flash, VisitProvider
    │   └── GuestLayout.tsx     Auth shell: brand text + locale switch header, centred white card
    ├── components/
    │   ├── Button.tsx          Button / ButtonLink / buttonClassName with 4 variants
    │   ├── Card.tsx            Card (div) / CardForm (form) sharing one surface class
    │   ├── Chip.tsx            Small pill, neutral|brand tone
    │   ├── Chrome.tsx          useDocumentLocale, LocaleSwitch, NavigationProgress (top bar), FlashMessage
    │   ├── CommandPalette.tsx  ⌘K dialog: filter nav links + "search customers/requests for …"
    │   ├── ConfirmDialog.tsx   Modal with message + cancel/confirm buttons
    │   ├── DataTable.tsx       Card-wrapped <table> with header row from string[]
    │   ├── EmptyState.tsx      Dashed card with inbox icon, title, hint
    │   ├── FilterBar.tsx       Card + GET form that serialises non-empty fields to the query string
    │   ├── FormField.tsx       <label> wrapper (label, child, error) + `controlClassName` string
    │   ├── Initials.tsx        Round avatar with up to two initials
    │   ├── KpiCard.tsx         Metric card: label, value, optional icon with tone; primary|quiet size
    │   ├── LoadingState.tsx    Skeleton card with label
    │   ├── PageHeader.tsx      h1 + subtitle + action slot
    │   ├── Pagination.tsx      Renders Laravel paginator links (raw HTML labels)
    │   ├── PriorityBadge.tsx   low/normal/high/urgent pill
    │   ├── RecordList.tsx      Switches error → loading → empty → children using VisitState
    │   ├── SearchInput.tsx     Uncontrolled text input with controlClassName
    │   ├── Skeleton.tsx        Shimmer bar
    │   ├── StatusBadge.tsx     Status → tone map (30 keys) + translated label
    │   └── VisitState.tsx      Context tracking Inertia visit loading/failed for preserveState visits
    └── pages/                  41 pages (see §4)
        ├── Dashboard.tsx, Ai/Index, Dispatch/Index
        ├── Auth/{Login,Register,ForgotPassword,ResetPassword}
        ├── Organizations/{Index,Settings}, Users/Index, Branches/{Index,Form}
        ├── Customers/{Index,Form,Show}, Services/{Index,Form}, Technicians/{Index,Form,Show}
        ├── Requests/{Index,Form,Show}, Appointments/{Index,Form}, WorkOrders/{Index,Form,Show}, Tasks/{Index,Form}
        ├── Invoices/{Index,Form,Show}, Payments/{Index,Form,Show}
        └── WhatsApp/{Index,Show,Settings}
```

Pages define their layout with `Page.layout = (page) => <AppLayout>{page}</AppLayout>`, so each page sets its own layout and there is no default.

---

## 2. Design system

### 2.1 Where things are defined

| Concern | Location | Notes |
|---|---|---|
| Theme tokens | `app.css` `@theme` (lines 10–64) | Tailwind v4 CSS-first config; there is no JS config file |
| Fonts | `app.css:11`, `app.css:66-68`, `app.blade.php:7-8` | Inter first in LTR. IBM Plex Sans Arabic first under `html[dir=rtl]`. Loaded from fonts.bunny.net (400–700) |
| Global element styles | `app.css` `@layer base` (lines 70–212) | Body gradient, button/link overrides, inputs, cards, tables, focus outline, skeleton keyframes, `date-mask` |
| Component styles | Inline Tailwind strings in `components/*.tsx` | `Button.variants`, `Card.surface`, `controlClassName`, badge tone maps |
| Page-level styles | Inline Tailwind in every page | Many one-off sizes, radii and shadows (see §5) |

### 2.2 Colours

**Semantic tokens defined but never used:** `canvas #f6f8fb`, `ink #0f172a`, `ink-secondary #475569`, `ink-muted #94a3b8`, `line #e2e8f0`, `brand #2563eb`, `brand-strong #1d4ed8`, `brand-soft #eff6ff`. They appear in 0 TSX files. `brand-strong` is referenced only once, inside `app.css`.

**Overridden Tailwind scales.** These are the real palette, but the names are misleading:

| Class family | Actually renders | Problems |
|---|---|---|
| `zinc-*` | Tailwind slate values | `zinc-100` = `zinc-50` (#f8fafc) and `zinc-300` = `zinc-200` (#e2e8f0). Two steps of the grey ramp are lost, so `bg-zinc-100` hover states and `border-zinc-300` borders look identical to the step below. `zinc-950` = `zinc-900`. |
| `teal-*` | Blue | `teal-800` = #2563eb = `teal-600`. The "primary" button is blue-600, not a dark shade. `teal-400/500` are undefined, so stock Tailwind teal would appear if anyone used them. |
| `green/amber/red/sky-*` | Partial custom ramps | `red-*` is actually rose (#e11d48). Only the steps in use are overridden. |
| `blue-*`, `indigo-*`, `violet-*`, `emerald-*`, `cyan-*` | Stock Tailwind | Used directly by AppLayout, Dashboard, CommandPalette, Initials, KpiCard and the Dispatch skill accents |

**Hardcoded hex values:**

- `app.css`: about 20 raw hex/rgb values in base styles (`#f6f8fb`, `#2563eb`, `#1d4ed8`, `#334155`, `#cbd5e1`, `#eef2f6`, `#3b82f6`, `#4f46e5`, and others).
- TSX: Dashboard `ColumnChart` uses `#60A5FA`, `#2563EB`, `#e8eef6`, `#e2e8f0` (5 hex values in 1 file).

**Status colours** are in `StatusBadge.tsx`: green for done/paid/active, sky for in-flight, amber for waiting/draft, red for cancelled/overdue, zinc for inactive. `PriorityBadge.tsx` has its own map, with no ring, unlike StatusBadge.

### 2.3 Typography

- Family: `font-sans` on `<body>`. `font-feature-settings: 'cv11','ss01'` (these are Inter features and do nothing in Plex Arabic).
- There is no type scale. Arbitrary sizes are used directly: `text-[26px]` (h1 in PageHeader and Dashboard), `text-[30px]` (KpiCard), `text-[15px]` (Dashboard card titles), `text-[11px]`, `text-[10px]`. That is 18 arbitrary `text-[Npx]` uses in 5 files. Section headings vary between `font-medium` (detail pages), `text-sm font-semibold` (Dispatch, AI) and `text-[15px] font-semibold` (Dashboard).
- `uppercase` with `tracking-wide` is used for eyebrow labels (sidebar groups, Dashboard date and "secondary"). Uppercase does nothing in Arabic, and the letter-spacing harms Arabic glyph joining.

### 2.4 Spacing, radius, shadow

- **Page padding:** `main` is `max-w-[1280px] px-4 py-8 sm:px-6 lg:px-8`. Card padding varies: `p-4`, `p-5`, `px-4 py-4`, `px-5 py-4`, `p-4 sm:p-5`. Gaps between sections vary: Dashboard uses `space-y-6` with `gap-3`, detail pages use `mb-6`, and FilterBar uses `mb-4`.
- **Radius:** 6 different values are in use: `rounded-md` (15 uses in 8 files, but forced to 10px by the CSS override on buttons), `rounded-lg` (8), `rounded-[10px]` (10), `rounded-xl` (11), `rounded-2xl` (12), and `rounded-full`. Cards are `rounded-2xl` through `Card`, but the AI sidebar is `rounded-xl`, the GuestLayout card is `rounded-2xl` without a shadow, and the WhatsApp inputs are `rounded-lg`.
- **Shadow:** `Card` uses an arbitrary two-layer shadow. 9 other arbitrary `shadow-[…]` values are spread across 6 files, plus 23 plain `shadow-sm/lg/xl` uses in 5 files (Dispatch, AI and WhatsApp add `shadow-sm` to Cards, which overrides the Card's own shadow). `app.css:152-157` also forces a shadow on any `.rounded-lg.border.bg-white` element.

### 2.5 Shared components: props and usage

Usage = JSX occurrences (files) outside the component's own file.

| Component | Props | Usage | Notes |
|---|---|---|---|
| `Button` | `variant?: primary\|secondary\|ghost\|danger` + all `<button>` attrs; `type` defaults to `button` | 53 (35 files) | `danger` variant: **0 uses**. All destructive confirms use `primary`. |
| `ButtonLink` | `variant?` (default `primary`) + `InertiaLinkProps` | 28 (18) | The default differs from Button (`secondary`), so pages pass `variant` inconsistently |
| `buttonClassName` | `(variant, className)` | 1 file (Button itself) | Exported, unused elsewhere |
| `Card` | `className` + div attrs | 38 (19) | Imported but unused in **12 Form pages** |
| `CardForm` | `className` + form attrs | 15 (15) | Used by every Form page and the settings pages |
| `Chip` | `tone?: neutral\|brand`, children | 7 (4) | Customers, Requests, Technicians, Dispatch |
| `CommandPalette` | `open, onClose, links, canSearchCustomers, canSearchRequests` | 1 (AppLayout) | |
| `ConfirmDialog` | `open, title, confirmLabel, cancelLabel, onConfirm, onCancel` | 8 (8) | Branches, Users, Customers, Services, Technicians, WorkOrders/Show, Invoices/Show, Payments/Show |
| `DataTable` | `headers: string[]`, children (rows) | 9 (9) | Not used by Branches, Users, Invoice items or the Dispatch technicians table (hand-rolled tables) |
| `EmptyState` | `title, description?` | 13 (11) | Always the same Inbox icon, with no call to action |
| `FilterBar` | children | 9 (9) | Hardcoded `md:grid-cols-4`. Pages use `md:col-span-4` on the submit button in 4 places and not in the others. |
| `FormField` | `label, error?, children` | 113 (17) | No `required`, no hint, no `id`/`aria-describedby` |
| `controlClassName` | string constant | 179 refs (28 files) | |
| `Initials` | `name, className?` | 3 (3) | Dashboard, Technicians/Index, Dispatch. AppLayout and WhatsApp/Index build their own avatars. |
| `KpiCard` | `label, value, icon?, tone?, size?` | 13 (1: Dashboard) | Hover lift on a non-interactive card |
| `LoadingState` | `label` | 1 (RecordList) | |
| `PageHeader` | `title, subtitle?, action?` | 34 files | Dashboard builds its own header |
| `Pagination` | `links` | 9 (9) | Uses `dangerouslySetInnerHTML` on Laravel labels |
| `PriorityBadge` | `value` | 8 (7) | |
| `RecordList` | `empty, children` | 6 (6) | Customers, Technicians, Requests, Appointments, WorkOrders, Dispatch. **Not** used by Services, Tasks, Invoices, Payments, Branches or Users, so those pages show no loading or error state. |
| `SearchInput` | `name?, defaultValue?, placeholder` | 9 (8) | No `aria-label` (placeholder only) |
| `Skeleton` | `className?` | 3 (LoadingState) | |
| `StatusBadge` | `value, label?` | 33 (20) | Also misused for payment **method** (Payments Index/Show) |
| `VisitProvider` / `useVisitState` | — | AppLayout / RecordList | |
| `LocaleSwitch`, `FlashMessage`, `NavigationProgress`, `useDocumentLocale` | — | 2 layouts each | |
| `optionLabel` | `(labels, value)` | 55 refs (22) | Silently falls back to the raw key |

**Missing shared primitives**, which pages re-implement inline: `Select`, `Textarea`, `Checkbox`, `DateInput`, `Money`, `DateTime`, `DescriptionList`/`DetailItem`, `Section`/`CardHeader`, `Avatar`, `Drawer`/`Sheet`, `Toast`/`Alert`, `Tabs`, `Ring`/`ProgressBar`, `Timeline`, `ChatBubble`, `IconButton`, `Breadcrumbs`.

---

## 3. Layouts and navigation

### 3.1 App shell (`AppLayout.tsx`)

- **Sidebar**: `fixed start-0 w-64`, white, `border-e`.
  - The top block is a hardcoded "O" gradient tile, the "OpsAI" eyebrow and the organization name. It links to `/organizations` ("switch organization").
  - The nav groups are collapsible, and their state is stored in `localStorage` (`opsai.nav.groups`).
  - The active item is `bg-blue-50 text-blue-700` with a 2px start bar.
  - The footer shows a user card (initial, name, translated role).
- **Top bar**: `sticky h-14`, translucent white with blur. It contains the mobile menu button, the current page label (taken from nav labels; it falls back to "Dashboard" for any page not in the nav, such as `/organizations`), a ⌘K search pill (md+) or a search icon (mobile), the locale switch, and the account menu (name, email, org · role, sign out).
- **Main**: `lg:ps-64`, `max-w-[1280px]`. `FlashMessage` (green success only) sits above the page.
- **Print**: the sidebar and header are hidden. Only Invoices/Show adds print styling.

### 3.2 Sidebar items and visibility per role

Items are filtered by `permissions` (`'*'` for owner).

| Group | Item → route | Permission | Owner | Admin | Manager | Dispatcher | Technician | Accountant | Cust. service |
|---|---|---|---|---|---|---|---|---|---|
| Overview | Dashboard `/` | always | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | Assistant `/ai` | always | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | WhatsApp `/whatsapp` | whatsapp.view | ✓ | ✓ | ✓ | ✓ | – | – | ✓ |
| Operations | Branches | branches.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | Customers | customers.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | Services | services.view | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ |
| | Technicians | technicians.view | ✓ | ✓ | ✓ | ✓ | – | – | – |
| | Requests | requests.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | Appointments | appointments.view | ✓ | ✓ | ✓ | ✓ | ✓ | – | ✓ |
| | Dispatch | appointments.view OR work_orders.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| | Work orders | work_orders.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | – |
| | Tasks | tasks.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Finance | Invoices | invoices.view | ✓ | ✓ | ✓ | ✓ | – | ✓ | ✓ |
| | Payments | payments.view | ✓ | ✓ | ✓ | ✓ | – | ✓ | ✓ |
| Workspace | Team `/users` | users.view | ✓ | ✓ | ✓ | – | – | – | – |
| | Organization `/organization` | organization.view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Observations:

- **Every role gets the same nav order.** A technician sees 10 items, including Branches, Dispatch and Organization, but has no "My jobs" or "Today" entry.
- **An accountant sees Dispatch** (via `work_orders.view`) and Requests before Finance.
- **Every role sees Organization settings.** For most roles it is a fully disabled form.
- **The WhatsApp Settings button** on `/whatsapp` is shown to anyone with `whatsapp.view`, whatever they can manage.
- **WhatsApp settings** (`/settings/integrations/whatsapp`) is not in the nav.

### 3.3 Mobile behaviour

- Below `lg` the sidebar becomes an off-canvas drawer (`max-lg:-translate-x-full rtl:max-lg:translate-x-full`) with a blurred backdrop and body scroll lock. It closes on Escape (which also returns focus to the menu button) or when a link is clicked.
- The drawer is **not a modal dialog**. It has no `role="dialog"` and no focus trap, so Tab moves into the page behind it.
- The user name in the top bar is hidden below `sm`. The search pill becomes an icon below `md`.
- Each page handles its own responsive layout (see §6).

### 3.4 Guest shell (`GuestLayout.tsx`)

The header shows "OpsAI" text and the locale switch. Below it is a centred `max-w-md` white card with a border and **no shadow** (unlike every other card) on `bg-zinc-100`. There is no illustration, brand mark, tagline or footer. Register puts 9 fields in this narrow column.

---

## 4. Pages

Legend for states: **L** = loading, **E** = empty, **Err** = error. `RecordList` provides L/E/Err for filter visits only (`preserveState`). A full navigation shows only the 2px top progress bar.

### Core

| Page | Route | Main components | Props from backend | States |
|---|---|---|---|---|
| Dashboard | `GET /` | KpiCard×13, Card, Initials, StatusBadge, ButtonLink; local `DateField`, `ColumnChart`, `Ring` | `metrics` {8 counts, requests_by_day[], work_orders_by_status[], technician_workload[{name,count}]}, `finance` (null if no access) {currency, revenue, invoiced, paid, outstanding, overdue, todays_payments, by_day[], invoice_status[]}, `branches`, `filters` | E per chart ("emptyChart"), no L/Err |
| Assistant | `GET /ai/{conversation?}` | Card, PageHeader, EmptyState, Button; chat via `POST /api/ai/chat` | `conversations[{id,title}]`, `active`, `messages[{id,role,content,metadata}]`, `pendingAction{id,tool_name,status,preview[]}`, `branches`, `branchId` | E (no thread), L ("thinking" text), Err (alert + retry) |
| Dispatch | `GET /dispatch` | PageHeader, Card, Chip, EmptyState, Initials, PriorityBadge, RecordList, StatusBadge; local `Ring`, `Drawer`, `TechnicianHeading` | `board` {date, timezone, now, is_today, hours{work/grid start/end}, nearest_day, filters, branches, can_create_*, counts{total,completed,unassigned,overdue}, technicians[{…,skills,availability,utilization,appointments[],work_orders[]}], unassigned[]} | E (day, filtered, technicians, unassigned), L/Err via RecordList, red toast for action errors |

### Auth (GuestLayout)

| Page | Route | Props | States |
|---|---|---|---|
| Login | `/login` | none | field errors only |
| Register | `/register` | `countries{code:label}`, `currencies[]`, `locales[]` | field errors |
| ForgotPassword | `/forgot-password` | none | field errors, flash `status` |
| ResetPassword | `/reset-password/{token}` | `token`, `email` | field errors (none for confirmation field) |

### Workspace

| Page | Route | Main components | Props | States |
|---|---|---|---|---|
| Organizations/Index | `/organizations` | PageHeader, Card, Button | `organizations[{id,name,slug,country,status,role}]` | **no E** |
| Organizations/Settings | `/organization` | CardForm, FormField | `organization{…, tax_rate}`, `countries`, `currencies`, `locales`, `statuses`, `canUpdate`, `canChangeStatus` | field errors |
| Users/Index | `/users` | CardForm (add form), Card + raw table, ConfirmDialog, EmptyState | `members[{id,name,email,role,branch_ids}]`, `branches`, `canManage`, `assignableRoles[]` | E, no L/Err. **No edit UI** although `PUT /users/{user}` exists |
| Branches/Index | `/branches` | PageHeader, Card + raw table, StatusBadge, ConfirmDialog | `branches[{id,name,code,city,country,status}]`, `canManage` | E only |
| Branches/Form | `/branches/create`, `/{id}/edit` | CardForm, FormField | `branch|null`, `countries`, `statuses` | field errors |

### Operations

| Page | Route | Main components | Props | States |
|---|---|---|---|---|
| Customers/Index | `/customers` | FilterBar, SearchInput, DataTable, Chip, StatusBadge, Pagination, RecordList, ConfirmDialog | `customers` Paginator<{id,name,phone,type,city,status,branch}>, `filters`, `branches`, `canManage` | L/E/Err |
| Customers/Form | `/customers/create`, `/{id}/edit` | CardForm (2-col), FormField | `customer|null`, `branches` | field errors |
| Customers/Show | `/customers/{id}` | Card, local `Related`, PriorityBadge, StatusBadge | `customer{…,branch}`, `requests[]`, `workOrders[]`, `appointments[{id,scheduled_start,status}]`, `activities[{action,description,created_at,actor}]`, `finance|null{totals, invoices[], payments[]}`, `canManage` | per-section "none" |
| Services/Index | `/services` | FilterBar, DataTable, StatusBadge, Pagination, ConfirmDialog, EmptyState | `services` Paginator<{name,category,base_price,estimated_duration,status}>, `filters`, `canManage` | E only |
| Services/Form | `/services/create`, `/{id}/edit` | CardForm | `service|null` | field errors |
| Technicians/Index | `/technicians` | FilterBar, DataTable, Initials, Chip, StatusBadge, RecordList, ConfirmDialog | Paginator<{employee_code, availability_status, status, user, branch, skills[]}>, `filters`, `branches`, `canManage` | L/E/Err |
| Technicians/Form | `/technicians/create`, `/{id}/edit` | CardForm, skills repeater (no add/remove) | `technician|null`, `branches`, `members`, `services` | field errors |
| Technicians/Show | `/technicians/{id}` | Card, StatusBadge | `technician{employee_code,availability_status,hourly_rate,status,user{name,email},branch,skills[{skill,service}]}`, `canManage` | none |
| Requests/Index | `/requests` | FilterBar, DataTable, PriorityBadge, StatusBadge, Chip, RecordList | Paginator<{subject,status,priority,source,customer}>, `filters`, `branches`, `canManage` | L/E/Err |
| Requests/Form | `/requests/create`, `/{id}/edit` | CardForm (2-col) | `serviceRequest|null`, `branches`, `customers`, `services` | field errors |
| Requests/Show | `/requests/{id}` | Card×4, PriorityBadge, StatusBadge | `serviceRequest` (**full Eloquent model** + customer, service, branch, appointments.technician.user, workOrder.technician.user, activities.actor), `canManage` | per-section "none" |
| Appointments/Index | `/appointments` | FilterBar, DataTable, StatusBadge, RecordList | Paginator<{scheduled_start, scheduled_end, status, customer, technician.user}>, `filters`, `technicians`, `branches`, `canManage` | L/E/Err |
| Appointments/Form | `/appointments/create`, `/{id}/edit` | CardForm | `appointment|null`, `branches`, `customers`, `technicians`, `requests` | field errors |
| WorkOrders/Index | `/work-orders` | FilterBar, DataTable, PriorityBadge, StatusBadge, RecordList | Paginator<{title,status,priority,customer,technician.user}>, `filters`, `branches`, `canManage` | L/E/Err |
| WorkOrders/Form | `/work-orders/create` | CardForm | `branches`, `customers`, `technicians`, `requests[{id,subject,customer_id}]`, `defaults` | field errors |
| WorkOrders/Show | `/work-orders/{id}` | Card, CardForm (transition), ConfirmDialog | `workOrder` (**full model** + customer, technician.user, serviceRequest, appointment, branch), `invoice|null`, `transitions[]`, `canTransition`, `canInvoice` | none |
| Tasks/Index | `/tasks` | FilterBar, DataTable, PriorityBadge, StatusBadge, EmptyState | Paginator<{title,priority,status,due_at,assignee,latest_activity}>, `filters`, `canManage` | E only |
| Tasks/Form | `/tasks/create`, `/{id}/edit` | CardForm | `task|null`, `branches`, `members` | field errors |

### Finance

| Page | Route | Main components | Props | States |
|---|---|---|---|---|
| Invoices/Index | `/invoices` | FilterBar (7 controls in a 4-col grid), DataTable, StatusBadge, EmptyState | Paginator<{invoice_number,status,issue_date,due_date,currency,total,amount_due,customer}>, `filters`, `customers`, `canManage` | E only |
| Invoices/Form | `/invoices/create`, `/{id}/edit` | CardForm, line-item repeater (add only, no remove, no totals) | `invoice|null`, `branches`, `customers`, `services`, `workOrders`, `defaults` | field errors (none per line item) |
| Invoices/Show | `/invoices/{id}` | PageHeader (4 actions), Card×4, raw items table, ConfirmDialog; print styles | `invoice{…totals, items[], payments[], activities[]}`, `canManage`, `canPay` | per-section "none" |
| Payments/Index | `/payments` | FilterBar (search only), DataTable, StatusBadge×2, EmptyState | Paginator<{amount,currency,method,status,reference,paid_at,invoice}>, `filters`, `canManage` | E only |
| Payments/Form | `/payments/create` | CardForm | `invoices[{id,invoice_number,amount_due,currency}]`, `defaults` | field errors |
| Payments/Show | `/payments/{id}` | Card, StatusBadge×2, ConfirmDialog | `payment{amount,currency,method,status,reference,paid_at,notes,invoice{customer},creator}`, `canRefund` | none |

### WhatsApp

| Page | Route | Main components | Props | States |
|---|---|---|---|---|
| WhatsApp/Index | `/whatsapp` | PageHeader, CardForm (simulator), Card list, StatusBadge, EmptyState | Paginator<{customer,phone,status,unread_count,last_message_at,preview}>, `simulation` | E. **No pagination rendered** although the payload is paginated (20/page) |
| WhatsApp/Show | `/whatsapp/{id}` | PageHeader, Card (bubbles), StatusBadge, EmptyState | `conversation{customer,phone,status}`, `messages[{direction,author,text,status,created_at}]`, `canReply`, `simulation` | E, human-required banner |
| WhatsApp/Settings | `/settings/integrations/whatsapp` | CardForm with **raw `<label>`s** (not FormField) | `integration|null{provider,status,phone_number_id,display_phone,business_account_id,token_hint,secret_hint,verify_token_set}`, `status` | **No field errors rendered at all** |

---

## 5. Consistency problems

### 5.1 Ad-hoc or duplicated styling

- **Buttons styled two ways.** `Button` uses `rounded-[10px] min-h-10`, while `app.css:99-129` restyles any `a|button.rounded-md.border` and `.rounded-md.bg-teal-800/zinc-900` with a forced gradient. This hidden second button system makes `bg-teal-800` look different on a `<button>` than on a `<span>` (for example the Pagination active state and the WhatsApp unread badge).
- **Cancel "buttons" are bare links:** `<Link className="px-4 py-2 text-sm text-zinc-700">` in 12 forms. 2 of them use `rounded-md`, which pulls in the CSS override; the other 10 don't, so their padding differs.
- **Text-link actions** (`text-teal-800`, 19 uses in 15 files) sit next to `ButtonLink variant="ghost"` actions for the same purpose:
  - Services/Index: `<Link>` Edit + `<Button ghost>` Archive in one cell, with different heights.
  - Branches/Index and Users/Index: text "Edit"/"Archive"/"Remove" with no button styling.
  - Invoices, Payments and Tasks: text "View"/"Edit". Customers, Technicians, Requests, Appointments and WorkOrders: ghost buttons.
- **Ad-hoc form controls that bypass `controlClassName`:**
  - Services/Index:52 and Tasks/Index:44: `rounded-md border border-zinc-300 px-3 py-2` status select, with no aria-label.
  - WhatsApp/Settings: 7 inputs with `rounded-md border border-zinc-300 px-3 py-2`.
  - WhatsApp/Index and Show: `min-h-11 rounded-lg border border-zinc-200`, which is a different height and radius from every other input (min-h-10, 10px).
  - Native checkboxes are unstyled (Login, Invoices filter).
- **Tables:** 4 hand-rolled tables (Branches, Users, Invoice items, Dispatch technicians), each with different header padding (`py-3` vs `py-2.5` vs `py-2`), header weight and `bg-zinc-50` usage. Several pages add `border-b border-zinc-100` or `border-t` to `<tr>` on top of the global `table tbody tr` border, which doubles the rule.
- **Badges:** `StatusBadge` has a ring and `PriorityBadge` does not. Dispatch's unassigned count and WhatsApp's unread count are their own ad-hoc pills.
- **Avatars:** there are 5 implementations: `Initials`, the AppLayout sidebar and top-bar initial (`bg-blue-600`), the WhatsApp/Index initial (`bg-teal-50 text-teal-800`, `size-10`), the Dashboard hashed-tone Initials, and the "O" org tile.
- **Duplicated logic:**
  - `can()` is defined in AppLayout and Dashboard, and Dispatch re-implements it inline 3 times.
  - `Ring` is copied in Dashboard and Dispatch with different sizes and colours (`stroke-blue-600` vs `stroke-teal-700`).
  - `fill()` template helpers appear in Dashboard and CommandPalette.
  - Status option arrays are hardcoded per page; for example `['new','qualified',…]` is repeated in Requests Index and Form, and the work-order and appointment status lists are each repeated. These should come from the backend enums.
  - The skill list is hardcoded in Technicians/Form and Dispatch (`SKILLS`).
- **Dead or unused code:**
  - `Card` is imported but unused in 12 Form pages. `Button` is imported but unused in Branches/Index and the Customers, Requests and Technicians Show pages.
  - `Button.danger` is never used. The `buttonClassName` export is used only internally.
  - `t.dispatch.freeSlots`, `t.dispatch.area`, `t.dispatch.agenda` and `t.dashboard.subtitle` appear to be unused (worth a key-usage sweep).

### 5.2 Hardcoded colours

- `app.css` base layer: about 20 literal hex/rgb values (listed in §2.2), including the body gradient, button gradient, borders, focus ring and table row colours.
- Dashboard `ColumnChart`: 4 literal hex values.
- Raw stock palettes are used outside the token system: `blue-*` (19 uses in 5 files), `indigo/violet/emerald/cyan` (10).

### 5.3 Logical vs physical properties

This is the codebase's strongest area. There are **0** physical `ml-/mr-/pl-/pr-/left-/right-/text-left/right` classes and 56 logical `ms/me/ps/pe/start/end` uses. The remaining directional items:

- `ChevronLeft/Right` in Dispatch use `rtl:rotate-180` correctly. The sidebar chevron uses `-rotate-90 rtl:rotate-90` correctly.
- The `rounded-ee-md` / `rounded-es-md` bubble tails are logical and correct.
- `Pagination` prints Laravel's `«` / `»` labels, which point the wrong way in RTL and are in English.
- The Dashboard `ColumnChart` SVG always draws oldest-to-newest left-to-right. In RTL the date labels below it (an HTML flex row) are reversed, so **the date labels no longer line up with their bars in Arabic**.
- The Dispatch time grid has a `sticky start-0` hour column. That is logically correct, but in RTL the horizontal scroll container starts at the right edge, which needs manual testing.

### 5.4 Strings missing from i18n (ar and en)

JSX text literals are clean: no hardcoded English sentences in TSX. The gaps are elsewhere:

| Source | What users see in Arabic mode | Where |
|---|---|---|
| **Flash messages** | English ("Customer saved.", "Payment recorded.", …) | 34 `__('…')` calls in controllers; **no `lang/` directory exists** |
| **Validation errors** | English Laravel defaults | No `lang/ar/validation.php` |
| **Pagination labels** | "« Previous" / "Next »" | `lang/*/pagination.php` missing |
| **Missing `t.labels` keys** (so `optionLabel` falls back to the raw key) | `suspended` (it is in `common`, not `labels`), `open` (WhatsApp conversation status), `queued`/`sent`/`delivered`/`read`/`received` (WhatsApp message status), AI action statuses (`approved`, `rejected`, `executing`, `executed`), AI role `system` | StatusBadge, WhatsApp/Show, Organizations/Index |
| **Activity actions** | 17 of 18 action keys untranslated (`customer.created`, `request.status_changed`, `invoice.issued`, `payment.refunded`, `work_order.status_changed`, `technician.assigned`, `whatsapp.human_required`, …). Only `task.created` exists. | Customers/Show, Requests/Show, Invoices/Show, Tasks/Index |
| **AI tool names** | `pending.tool_name` (for example `create_task`) and the `toolsUsed` list | Ai/Index:604, :592 |
| **Service category** | Free text, shown raw in Dispatch chips (`job.category`) and Services/Index | |
| **Page `<title>`** | Always "OpsAI": **no page uses `<Head>`** | all pages |
| **Brand tile "O"**, `Ctrl K` | Not localised | AppLayout:202, :62 |
| Copy hardcoding a fact | `dispatch.workingDay` says "fixed 8-hour day" although `board.hours` is now configurable | en.ts:123 / ar.ts |

### 5.5 Raw values shown to users

**Dates and times shown as ISO or sliced strings** (only Dashboard uses `Intl`):

- Appointments/Index: `scheduled_start` and `scheduled_end` are printed raw (for example `2026-10-24T09:00:00+03:00`).
- Customers/Show: the appointment row label *is* the raw `scheduled_start`. Activities have `created_at` in the payload but it isn't shown.
- Requests/Show: `preferred_date` (raw ISO) + `preferred_time` (`HH:MM:SS`); appointment `scheduled_start` is raw.
- Tasks/Index: `due_at` raw.
- Invoices Index/Show: `issue_date.slice(0,10)` and `due_date.slice(0,10)` give `2026-10-24` in both locales.
- Payments/Show: `paid_at` raw. Payments/Index: `paid_at` is in the payload but not shown.
- Dispatch: `requested_at.slice(11,16)`, `nearest_day` raw (`2026-10-25`), and every time uses `slice(11,16)`.
- WhatsApp/Index: `last_message_at` is in the payload but not shown. WhatsApp/Show: `created_at` is in the payload but not shown.

**Role and status keys shown raw:**

- Organizations/Index: `{role} · {country} · {status}`, giving "owner · SA · active".
- Users/Index: role column **and** the role `<select>` options show `owner`, `customer_service`.
- Branches/Form: status options show `active`/`inactive`.
- Organizations/Settings: status options (`active`/`suspended`) and locale options (`ar`/`en`).
- Register: locale options `ar`/`en`.
- Requests/Show and Invoices/Show: `activity.action` printed raw, without even `optionLabel`.

**Money shown raw** (`"1250.00 SAR"`, no grouping, Latin digits and the currency code after the number in Arabic):

- Customers/Show balance, Invoices Index/Show, Payments Index/Show/Form, and Invoice items.
- Services/Index `base_price` has no currency at all. `estimated_duration` has no unit.
- Technicians/Show `hourly_rate` is unlabeled and has no currency.
- Only the Dashboard formats money with `Intl.NumberFormat`.

**Unlabeled detail values:**

- Customers/Show: phone, email, WhatsApp, address and branch are bare `<p>`s.
- Technicians/Show: email, code, branch, rate and skills.
- WorkOrders/Show: customer, technician, description, request subject.
- Payments/Show: customer, reference, `paid_at`, creator.

### 5.6 Inconsistent spacing

- Card padding: `p-4` (Filter, AI aside, Dispatch, WhatsApp), `p-5` (forms, details), `px-4 py-4` (Organizations), `px-5 py-4` (KPI).
- Section gaps: `mb-6` (details), `mb-4` (FilterBar, toast, flash), `space-y-6` with `gap-3` (Dashboard), `gap-4` (Dispatch, AI). Detail pages stack cards with `mb-6` but forms use `gap-4`.
- Form widths: `max-w-xl` (Payments, WhatsApp Settings, WO transition), `max-w-2xl` (Branches, Org Settings), `max-w-3xl` (all others). Forms are start-aligned, which leaves a large empty area on wide screens.
- Form layouts: one column (Services, Technicians, Appointments, WorkOrders, Tasks, Invoices) vs two columns (Customers, Requests, Users).
- Filter submit button: full-width row (`md:col-span-4`) on Customers, Technicians and Requests; a single cell on Services, Tasks, Appointments, WorkOrders, Invoices and Payments.

### 5.7 Overflow risks with long Arabic or Latin names

- **Table cells have no `truncate` or `max-w`, except** the Invoices customer column. Long customer names, subjects, emails and references in Customers, Requests, WorkOrders, Appointments, Users and Branches either wrap unevenly or push the table into horizontal scroll.
- **PageHeader h1** has `min-w-0` but no `break-words`, so a long request subject, work order title or customer name as the page title can overflow on mobile. The Dashboard greeting uses `truncate`, which hides most of a long Arabic name.
- **Missing `dir="auto"`** (only 7 uses, in AppLayout and Dashboard): user-entered names, subjects and notes in the opposite script render with wrong punctuation order. Examples are an English company name in Arabic UI ("Al Noor Trading.") and Latin phone numbers next to Arabic labels.
- **Phone numbers** have no `dir="ltr"`, so `+966 5x…` can render as `5x… 966+` in RTL.
- **Dispatch:** job titles are truncated in 10.5rem columns (fine), but the unassigned card title and the drawer title have no truncate or break. The mobile agenda `job.title` has no truncate.
- **Organizations/Index card:** `flex justify-between` with no `min-w-0`, so a long organization name squeezes the Open button.
- **WhatsApp:** the `preview` is truncated (good). Message bubbles use `break-words` (good).
- **The Invoice form line-item grid** (`sm:grid-cols-4`) gives the description the same width as quantity, so long descriptions are cramped.

---

## 6. RTL and responsive weaknesses

| Page | RTL | Mobile |
|---|---|---|
| Dashboard | Column chart date labels misaligned (§5.3). Uppercase and tracking on Arabic eyebrows. The greeting truncates long Arabic names. `ar-SA` currency gives Arabic-Indic digits for money but Latin digits in KPI counts, so digit styles are mixed. | Good. The 4→2→1 KPI grid works. The filter row wraps. |
| Dispatch | The day grid starts scrolled at the right edge, so check the sticky hour column. Raw `HH:MM` and the ISO `nearest_day` are fine, but the dates are not localised. | Has a dedicated agenda view below `lg`. The filter row of 5 controls wraps unevenly. The "technicians" table and overview rings below the board duplicate the board and push it far down. |
| Ai/Index | Bubbles use logical tails (good). | The conversation list stacks **above** the chat at full height, so on a phone you scroll past every conversation before reaching the composer. The thread is not a scroll region and the composer is not sticky. |
| WhatsApp/Show | Same bubble approach (good). Phone numbers are not wrapped in LTR. | The composer sits below all messages with no sticky positioning, and the conversation does not auto-scroll to the latest message. |
| All Index pages | — | The tables just scroll horizontally inside a card. There is no card or stacked-list mode, so on a 375px screen 6–7 columns plus action buttons require sideways scrolling. The FilterBar becomes 4–7 full-width controls stacked before any data appears. |
| Invoices/Index | — | The FilterBar has 7 controls in a 4-col grid, so the overdue checkbox and Search button sit in an orphaned second row. |
| Invoices/Show | Totals are listed in reading order rather than as an aligned totals block. Amounts are not aligned at the end. | 4 header actions wrap. The items table scrolls. |
| Invoices/Form | — | The line-item grid collapses to one column with placeholder-only labels, so it isn't clear which field is which. There is no remove button. |
| Users/Index | Raw English role keys in Arabic UI. | The native `<select multiple>` for branches is hard to use on touch screens. |
| Register | Locale options show `ar`/`en`. | A 9-field single column in a `max-w-md` card means a long scroll. Timezone is a free-text input. |
| Pagination (all) | `«`/`»` point the wrong way and are in English. | Fine (wraps). |
| Detail pages | Unlabeled bare values are harder to scan in RTL. | Two-column `<p>` grids collapse into one column of unlabeled text. |

---

## 7. Accessibility gaps

**Focus:**

- The global focus ring is `outline: 2px solid rgb(37 99 235 / 0.4)` (`app.css:206-211`). At 40% alpha on white, that is roughly 1.9:1 contrast, which fails WCAG 2.4.11/1.4.11 (3:1 for UI components).
- Inputs use a 3px ring at 40% alpha with a blue border. The border meets the requirement, but the ring alone doesn't.
- `controlClassName` sets `outline-none` and relies on the global `:focus` rule. That works, but `select[multiple]` and checkboxes get only the browser default.
- Dispatch job blocks use `focus-visible:outline-none focus-visible:ring-2 ring-teal-700`. That is fine, but it is the only place using the ring utility.

**Dialogs and overlays:**

- `ConfirmDialog`: no `aria-labelledby`/`aria-describedby`, no initial focus, no focus trap, no Escape handler, no focus return to the trigger. The confirm button for destructive actions is `primary` (blue) rather than `danger`.
- Dispatch `Drawer`: no `role="dialog"`, `aria-modal`, Escape handling or focus management. The backdrop is a full-screen `<button>`, which is a large, invisible focus stop.
- `CommandPalette`: has `role="dialog"`, `aria-modal`, `listbox`/`option` and `aria-activedescendant` (good). But the input has no `role="combobox"` or `aria-expanded`, there is no focus trap, and its backdrop button is labelled "Close menu".
- Mobile nav drawer: no focus trap (see §3.3).
- Account menu: `role="menu"`, but there is no arrow-key navigation and no focus moves into the menu.

**Labels:**

- `FormField` wraps the input in its `<label>`, so implicit labelling works. But the error `<span>` is not linked with `aria-describedby`, inputs never get `aria-invalid`, and required fields have no indicator.
- `SearchInput` (9 uses) has a placeholder only and no `aria-label`.
- Unlabeled selects: Services and Tasks status, Invoices status and customer, and the Dispatch drawer technician select (wrapped in a label, so OK).
- Invoice line-item inputs and the Technician skills repeater are labelled only by placeholder, or by nothing for the second select.
- The Dashboard `DateField` hides the native input text (`date-mask`, transparent colour) and overlays a formatted span. Screen readers announce the ISO value, and on mobile Safari the native picker shows transparent text.

**Keyboard:**

- Dispatch drag-to-reschedule is pointer-only. A keyboard alternative exists through the drawer form (good), but it isn't announced.
- The Dispatch toast has `role="status"` for errors. It should be `role="alert"`, and it never dismisses.
- AI "thinking" and new messages are not in an `aria-live` region. WhatsApp new messages are the same.

**Contrast** (estimated):

- `text-zinc-400` (#94a3b8) on white is about 2.6:1. It is used for meaningful text: sidebar group labels, Dashboard date eyebrow, chart axis labels, "none" placeholders, palette group names and `kbd`. This fails AA.
- `text-teal-100` on `bg-teal-800` (#dbeafe on #2563eb) for bubble metadata is about 4.0:1, which is borderline for 12px text.
- White on `bg-teal-800` (#2563eb) is 5.2:1, which passes. The CSS gradient ending at `#4f46e5` is fine.
- Badge text (`*-700` on `*-50`) is 4.5:1 or better, which passes.

**Other:**

- Charts (`ColumnChart` uses `role="img"`) have no `aria-label` or text alternative; the `<title>` tooltips per bar are mouse-only. `Ring` is `aria-hidden`, but its value is duplicated in text (OK).
- Status is shown by colour alone in a few places: the Dispatch availability dot on avatars and the job tints.
- There are no landmarks besides `nav`, `header` and `main`. Detail sections use `<h2>` inside cards (OK), but there is no skip link.
- `html lang` is set server-side and updated client-side (good).

---

## 8. The 10 weakest screens (visual)

1. **WorkOrders/Show**: the most important operational record looks the most bare. It has an unlabeled 2×3 grid of `<p>`s (customer, technician, description, request subject, two badges). `technician_notes` and `completion_notes` are in the payload but **not rendered**. There is no appointment time, no `started_at`/`completed_at`, no link to the request, no activity timeline, and no customer phone or address for a field visit. The status-transition form is a generic select instead of clear next-step buttons.
2. **Requests/Show**: the same unlabeled grid. The description sits in a grid cell next to badges. Appointment times are raw ISO and activities use raw action keys. "Create work order" is a small text link. There is no header summary (customer, priority, status, age).
3. **Payments/Show**: the title is the raw `"1250.00 SAR"`. It contains 8 unlabeled values, including a raw `paid_at` timestamp and the method shown as a status badge. There is no receipt layout and no link to the customer.
4. **Technicians/Show**: just a heading and an unlabeled 7-value grid. It has no schedule, current workload, utilization, recent jobs, contact or availability control, even though Dispatch computes all of these.
5. **Customers/Show**: a long stack of 6 near-identical cards. The contact block is unlabeled. Appointments are listed as raw ISO strings linking to the generic `/appointments` list. Money is unformatted. Activities have no timestamps. There is no summary header (open requests, balance, last contact) and no tabs.
6. **WhatsApp/Settings**: it ignores the design system completely (raw labels, `border-zinc-300` inputs, `p-4`). It shows **no validation errors**, no current connection status or last-verified state, no explanation of the fields, and no webhook URL to copy.
7. **Users/Index**: an "add member" form sits permanently above the list. The list has raw role keys and no avatar, branch or status column. There is no edit or role-change UI, and the branch multi-select is native. The remove action is red text.
8. **Organizations/Index**: plain cards with the raw `owner · SA · active`. There is no logo or initial, no current-org indicator and no empty state. "Open" is a primary button on every card.
9. **Invoices/Show**: totals are mixed into the metadata grid (`Subtotal: 1000.00`) instead of a right-aligned totals block. The items table has no currency, no tax column (even though `item.tax` is in the payload) and no alignment. Payments are listed as bare links and activities use raw keys. It is printable, but it doesn't look like an invoice.
10. **Tasks/Index**: the raw ISO `due_at`, no overdue highlighting, an ad-hoc status select, no loading or error state, and "Edit" as a text link. There is no quick-complete action and no grouping by due date or assignee.

Close behind: the **Auth pages**, which work but are plain: no brand, no shadow, and a 9-field Register in a narrow card with free-text timezone. Also **Services/Index** (price with no currency, duration with no unit, ad-hoc select) and **Appointments/Index** (raw ISO times in a table, with no calendar or day grouping).

---

## 9. Backend data gaps (UI ideas blocked by the payload)

| UI idea | Blocked because | Where |
|---|---|---|
| Localised flash, validation and pagination text | No `lang/ar/*.php` or `lang/ar.json` | app-wide |
| Status and option lists driven by enums (no hardcoded arrays) | Pages don't receive enum option lists; only Org Settings and Branches get `statuses` | Index filters, Forms |
| Readable activity feed ("Sara changed status from New to Scheduled · 2h ago") | Activities are sent as raw serialised models, so `created_at` and `properties` (from/to values) are probably present but aren't in the page types or rendered. There is no explicit, documented activity shape. Confirm the shape and expose it explicitly. | Requests/Show, Invoices/Show, Customers/Show |
| Index tables with dates, references and age | Requests rows lack `requested_at`, `reference`, `branch`. WorkOrders rows lack `reference`, `created_at`, scheduled time. Customers rows lack `email`, last activity, open request count. | Index pages |
| Customer picker with phone or search | `customerOptions()` returns only `{id, name}` for the whole organization (no search endpoint), so large organizations get huge selects | Requests/Appointments/WorkOrders/Invoices Forms |
| Technician profile with schedule, utilization and recent jobs | Technicians/Show receives only the profile; Dispatch-style metrics aren't exposed | Technicians/Show |
| Work order visit card (address, phone, map link, scheduled time) | Only the customer `name` is used. The full model is sent, but the frontend type ignores it, and the appointment and branch relations are loaded but not typed. | WorkOrders/Show |
| Work order timeline (accepted → on the way → arrived → completed) | No status-history or activities relation is loaded for work orders | WorkOrders/Show |
| Invoice totals with currency and tax per line | Line items have `tax`, but the page ignores it. The currency is only on the invoice (fine). There is no organization or branch letterhead (legal name, tax number, address) for print. | Invoices/Show |
| Payments list with customer and date | Payments/Index loads `invoice:id,invoice_number,customer_id` but not the customer name | Payments/Index |
| Users list with branch names, last login and status | `members` has `branch_ids` only (names must be joined client-side) and no last-seen or status | Users/Index |
| Organization switcher with the current org marked and a logo | `organizations[]` has no `is_current` or logo | Organizations/Index |
| WhatsApp settings status (connected, last verified, webhook URL) | `integration` has `status` but no `verified_at`, `last_error` or webhook URL | WhatsApp/Settings |
| WhatsApp message timestamps and delivery ticks | `created_at` is present ✓, but there are no separate `sent_at`/`delivered_at`/`read_at` (they exist in the DB, see `WhatsAppInboundProcessor:84-86`) | WhatsApp/Show |
| AI confirmation card with friendly action names | `pending_action.tool_name` is a code identifier, with no localised label or description | Ai/Index |
| Notification or bell in the top bar (overdue invoices, human-required chats) | No shared counts in `HandleInertiaRequests` | AppLayout |
| Technician "My day" view | Technicians land on the owner dashboard. There is no role-aware dashboard payload. | Dashboard |
| Dashboard trend deltas (vs last period) | Metrics are point-in-time only | Dashboard |
| Branded org tile instead of "O" | No organization logo or colour field | AppLayout |

There is also an over-fetching issue for later: `Requests/Show` and `WorkOrders/Show` serialise **full Eloquent models**, including the related customer's full record. The redesign is a good moment to replace these with explicit arrays or API Resources so the frontend types match the payload.

---

## 10. Prioritised redesign plan

Risk scale: **Low** = visual only, no behaviour change. **Med** = touches shared components or many files, so the e2e text and role selectors need re-checking. **High** = changes behaviour, routes or payloads.

Every step must keep `npm run types` and the Playwright suite green. The suites rely on role, label and text selectors (`Save`, `Create`, `View`, `Send`, `Simulate inbound message`, `Phone`, `Message`, combobox `Customer`, heading level 1) and 5 `data-testid`s (`ai-confirmation`, `customer-balance`, `dispatch-board`, `finance-metrics`, `whatsapp-message`). `tests/e2e/visual-step1.spec.ts` may hold screenshot baselines that will need regenerating.

### Step 1: Token foundation (no visual change yet)

- **Scope:**
  - Define a real semantic token set in `@theme`: surface/canvas, text ×3, border ×2, brand ×4, success/warning/danger/info × bg/fg/border, a radius scale (`--radius-control`, `--radius-card`), shadows (`--shadow-card`, `--shadow-overlay`) and a type scale (`--text-display/title/heading/body/caption`).
  - Keep the zinc and teal overrides temporarily so nothing breaks.
- **Files:** `resources/css/app.css`
- **Risk:** Low

### Step 2: Locale and format helpers

- **Scope:**
  - Add `lib/format.ts` with `formatDate`, `formatDateTime`, `formatTime`, `formatMoney`, `formatNumber` and `formatDuration`. They should be locale-aware (`ar`/`en`, a consistent numbering-system decision), timezone-aware (using `organization.timezone`), and extracted from the Dashboard helpers.
  - Add `lib/permissions.ts` with `can()`.
  - Add `lib/options.ts` holding the status, priority, source, skill and role arrays in one place, until step 9 replaces them with backend data.
- **Files:** new `lib/*`; `Dashboard.tsx` and `Dispatch/Index.tsx`, which are switched to the helpers
- **Risk:** Low

### Step 3: Fill the i18n gaps

- **Scope:**
  - Add the missing `t.labels` keys: `suspended`, `open`, WhatsApp message statuses, AI statuses, 17 activity actions, AI tool names, and `ar`/`en` language names.
  - Make `optionLabel` warn in development on a miss.
  - Create `lang/ar.json`, `lang/en.json`, `lang/ar/validation.php` and `lang/ar/pagination.php` (via `php artisan lang:publish`, then translate) so flash, validation and pagination text is localised.
  - Fix the `workingDay` copy.
- **Files:** `i18n/en.ts`, `i18n/ar.ts`, `lib/labels.ts`, new `lang/**`
- **Risk:** Low–Med. E2E tests that assert English flash text still pass in `en`; Arabic tests gain real text.

### Step 4: Core primitives rebuilt on tokens

- **Scope:**
  - Rewrite `Button` with sizes, an `IconButton`, a loading state, and `danger` wired to confirms.
  - Rewrite `Card` and add `CardHeader`/`Section`.
  - Rewrite `FormField`: generate `id`s, add `aria-describedby`, `aria-invalid`, `required` and `hint`.
  - Add `Input`, `Select`, `Textarea`, `Checkbox` and `DateInput` (replacing the `date-mask` trick), unify `StatusBadge` and `PriorityBadge` into `Badge`, and add `Avatar` (replacing the 5 avatar variants).
  - Add `Money`, `DateTime` and `DescriptionList`.
  - Then **delete the compound-selector overrides** in `app.css` (`button.bg-teal-800`, `a.rounded-md.border`, `.rounded-lg.border.bg-white`, and so on) and the zinc and teal palette remaps.
- **Files:** `components/*` (about 12 files), `app.css`
- **Risk:** Med. This is where hidden CSS overrides stop applying, so every page needs a visual pass. Keep the button text and labels identical for the e2e selectors.

### Step 5: Overlays and accessibility

- **Scope:**
  - Build one `Dialog`/`Sheet` primitive with a focus trap, Escape handling, initial focus, focus return, `aria-labelledby` and `inert` on the background.
  - Rebuild `ConfirmDialog` (with a destructive variant), the Dispatch `Drawer`, the `CommandPalette` (combobox pattern) and the mobile nav drawer on it.
  - Add `Toast`/`Alert` with `role="alert"` for errors and auto-dismiss, replacing the Dispatch toast and extending `FlashMessage` with error and info tones.
  - Replace the focus ring with a solid 2px brand ring and offset. Raise meaningful `zinc-400` text to the `text-muted` token at 4.5:1 or better.
  - Add a skip link.
- **Files:** `components/ConfirmDialog.tsx`, `CommandPalette.tsx`, `Chrome.tsx`, new `Dialog.tsx` and `Toast.tsx`, `layouts/AppLayout.tsx`, `pages/Dispatch/Index.tsx`, `app.css`
- **Risk:** Med

### Step 6: App shell refresh

- **Scope:**
  - Sidebar: token colours (drop the raw `blue-*`), a real org avatar (initial plus hashed tone until there is a logo field), and the active state on tokens.
  - Add a role-aware nav order. A technician gets "Today / My work orders" first and hides Branches, Organization and Dispatch. An accountant gets Finance first. This is still driven by `permissions`.
  - Add a page-title prop and `<Head>` on every page.
  - Top bar: show the page title or breadcrumbs from the page, not the nav label.
  - GuestLayout: brand mark, shadowed card and a two-column Register at `sm+`.
- **Files:** `layouts/AppLayout.tsx`, `layouts/GuestLayout.tsx`, `components/PageHeader.tsx` (add `breadcrumbs`, `meta`, `break-words`), all pages (one-line `<Head>`)
- **Risk:** Med. The nav change for technicians and accountants is a product decision, so confirm it first.

### Step 7: Index-page pattern

- **Scope:**
  - Define one list template: `PageHeader`, then a compact `FilterBar` (search plus a "Filters" popover on mobile, chips for active filters, auto-columns instead of a fixed 4), then `DataTable` v2.
  - `DataTable` v2 adds column config, `truncate` with `title`, `dir="auto"` cells, an end-aligned numeric column type, a row action menu, a stacked card layout below `md`, and skeleton rows.
  - Wrap every list in `RecordList` so Services, Tasks, Invoices, Payments, Branches and Users get loading and error states.
  - Format all dates and money through the step 2 helpers.
- **Files:** `components/DataTable.tsx`, `FilterBar.tsx`, `SearchInput.tsx`, `RecordList.tsx`, `Pagination.tsx` (own prev/next with icons and i18n), and the 11 Index pages (Branches, Users, Customers, Services, Technicians, Requests, Appointments, WorkOrders, Tasks, Invoices, Payments) plus WhatsApp/Index (add pagination)
- **Risk:** Med. Keep the "View"/"Create" link names and the `row` role.

### Step 8: Detail-page pattern (fixes 7 of the 10 weakest)

- **Scope:**
  - Define one record template: a header (title, status and priority badges, key meta, primary actions), a main column of `Section` cards using `DescriptionList` with labels, a side column (related records and money summary), and an `ActivityTimeline` component with localised actions and relative times.
  - Apply it to WorkOrders/Show (render the notes, link the request, show the appointment time, next-step buttons for transitions), Requests/Show, Customers/Show (summary header, tabs or sections), Technicians/Show, Payments/Show (a receipt layout) and Invoices/Show (an invoice layout with an end-aligned totals block and print letterhead).
- **Files:** new `DescriptionList.tsx`, `ActivityTimeline.tsx`, `Section.tsx`; the 6 Show pages
- **Risk:** Med. Keep `data-testid="customer-balance"` and the invoice links.

### Step 9: Forms pattern

- **Scope:**
  - Use sectioned forms (`Section` with a heading and a 2-col responsive grid), a sticky footer with Save and Cancel as a real secondary `Button`, required markers, and inline hints.
  - Use a searchable `Combobox` for customer, technician and request pickers.
  - Line-item editor for invoices: labelled columns, add and remove, running totals.
  - Technician skills repeater with add and remove.
  - Timezone and country `Select`s.
  - Users: an edit drawer for role and branches, and a multi-select chip picker instead of `<select multiple>`.
  - WhatsApp/Settings: moved onto `FormField`, with errors shown.
- **Files:** 12 Form pages, Users/Index, Organizations/Settings, WhatsApp/Settings, Register; new `Combobox.tsx`
- **Risk:** Med. Keep the "Save" button name and the combobox accessible name "Customer".

### Step 10: Feature screens

- **Scope:**
  - **Ai/Index:** a scrollable thread with a sticky composer, the conversation list in a `Sheet` on mobile, a friendly confirmation card, `aria-live`, and a textarea composer.
  - **WhatsApp/Show:** timestamps, delivery ticks, auto-scroll to the latest message, a sticky composer, and LTR-wrapped phone numbers.
  - **Dispatch:** move the technicians table and overview rings into a collapsible summary, use token colours, localise the date and `nearest_day`, show the skill label for the category, and add `dir` checks for the RTL grid scroll.
  - **Dashboard:** fix the RTL chart label alignment, add accessible chart summaries, remove `uppercase` for Arabic, and drop the KPI hover lift.
- **Files:** `pages/Ai/Index.tsx`, `pages/WhatsApp/Show.tsx`, `pages/WhatsApp/Index.tsx`, `pages/Dispatch/Index.tsx`, `pages/Dashboard.tsx`
- **Risk:** Med. Dispatch drag and AI confirm flows are covered by e2e tests.

### Step 11: Backend payload upgrades (unblocks §9)

- **Scope:**
  - Replace full-model serialisation in Requests/Show and WorkOrders/Show with explicit arrays.
  - Add `created_at` and `properties` to activities, references and dates to index rows, and a customer name to payments.
  - Share enum option lists.
  - Add a customer search endpoint for the combobox, WhatsApp `sent_at`/`delivered_at`/`read_at`, integration `verified_at` and webhook URL, an `is_current` flag on organizations, top-bar counts (overdue invoices, human-required chats), and a technician "my day" payload.
- **Files:** controllers (`ServiceRequestController`, `WorkOrderController`, `CustomerController`, `PaymentController`, `OrganizationController`, `OrganizationUserController`, `WhatsAppInboxController`, `WhatsAppIntegrationController`, `DashboardController`), `SharesLookups`, `HandleInertiaRequests`, plus Feature tests
- **Risk:** High (payload contracts and authorization). Can run in parallel with steps 7–10, page by page: each page's backend change should ship with that page's redesign.

### Step 12: Clean-up and guardrails

- **Scope:**
  - Remove unused imports (`Card` in 12 forms, `Button` in 4 pages) and unused i18n keys.
  - Remove the `--color-zinc/teal` remaps once no class depends on them.
  - Add an ESLint or Tailwind lint rule (or a CI grep) banning raw `blue-*`/`teal-*`, `rounded-[…]`, `shadow-[…]` and `text-[Npx]` in pages.
  - Add an i18n key-usage check.
  - Add Playwright RTL screenshots at 375px and 1280px for one Index, one Show, one Form, Dashboard and Dispatch.
- **Files:** pages, `app.css`, lint config, `tests/e2e`
- **Risk:** Low

**Suggested order:** steps 1–3 first (safe, unblock everything). Then 4–5 together (this is where the hidden CSS overrides are removed, so do one full visual QA pass). Then 6, then 7–10 page family by page family, with the matching part of 11 alongside each. Finish with 12.
