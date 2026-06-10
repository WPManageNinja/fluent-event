# Clarifying Questions — for the Team Lead

> Open questions that need a decision before the related spec can be finalized. Each has context + the decision needed. Lead fills in **Answer**. Once answered, update the file(s) listed under **Affects**.

---

## Q1 — Is there an "all members" page (member directory)?

**Context:** FR-3 (Member Management) specs a _management_ screen — gated, for managers only, to administer members. That's distinct from a public-facing **member directory** where ordinary members browse who else is in the space (like a community members list).

**Questions:**

1. Does an "all members" page exist at all?

- Recommendation: Yes

2. If yes, **who can view it?**
   - Public (anyone, logged-out included)
   - Logged-in users only
   - Specific roles / managers only

- Recommendation: Only specific roles / managers

3. What's visible per member (avatar, display name, profile link) vs hidden (email, status)?

- Recommendation: Avatar, display name, profile link, status, email (since we are showing this on permission already)

**Answer:**
_(team lead)_

**Affects:** [member-management/member-management.md](functional-requirement-parts/member-management/member-management.md) (add an all-members directory FR, or note management-only), [settings/settings.md](functional-requirement-parts/settings/settings.md) (FR-4.6 privacy — who can view the member list), [02-functional-requirements.md](02-functional-requirements.md) (index)

---

## Q2 — How many settings sections, and which ship in v1?

**Context:** FR-4 currently defines **7** settings sections. Need to confirm the set is complete and which are v1 vs later.

**Current sections:**

1. General (title, logos, permalink, slug)
2. Customization (dark mode, default layouts)
3. Feature addons (module toggles, integrations)
4. Managers (assign users + role)
5. Access management (external plugin data access)
6. Privacy (who can do what)
7. Email settings (customizable templates)

**Questions:**

1. Is this the complete set, or are sections missing (e.g. payments, notifications, integrations as its own section)?

- Recommendation: Notifications, payments

2. Any to drop or merge (e.g. Privacy + Access management into one)?

- Recommendation: We can merge those

3. Which ship in **v1** vs deferred?

- Recommendation: General, basic customization, managers, privacy, emails

**Answer:**
_(team lead)_

**Affects:** [settings/settings.md](functional-requirement-parts/settings/settings.md) (FR-4 section list), [02-functional-requirements.md](02-functional-requirements.md) (index)

---

## Q3 — How many email templates do we need?

**Context:** The catalog ([email-settings.md](functional-requirement-parts/settings/email-settings.md)) currently lists **18** emails across account/auth, events, membership, and digest. Need the final set + v1 cut.

**Questions:**

1. Is the 18-email list complete, or missing any?

- Recommendation: We can start with basic customization.

2. Which ship in **v1** vs later? (digest, new-event announce, declined are defer candidates.)

- Recommendation: Auth related email, create event, upcoming event.

**Answer:**
_(team lead)_

**Affects:** [settings/email-settings.md](functional-requirement-parts/settings/email-settings.md) (catalog), [settings/settings.md](functional-requirement-parts/settings/settings.md) (FR-4.7)

---

## Q4 — Activity tracking scope

**Context:** FR-5 keeps a history of user actions (an activity log). Before building, we need to decide what to track, how long to keep it, and who can see it.

**Questions:**

1. Should we record failed login attempts and users' IP addresses?

- Recommendation: Yes.

2. When an action happens, do we save it right away, or in the background?

- Recommendation: In the background (Fire & Forget type, Asynchornous)

3. Can members see their own activity, or is the log only for managers/admins?

- Recommendation: Only managers or admin.

4. Should old records be deleted automatically on a schedule (e.g. delete logs older than 60 days)? Should some types (like security events) be kept longer?

- Recommendation: Yes, we can keep last 6 months data (max), then delete older.

**Answer:**
_(team lead)_

**Affects:** [activity-tracking/activity-tracking.md](functional-requirement-parts/activity-tracking/activity-tracking.md)

---

## Q5 — Events scope

**Context:** FR-6 specs the core event entity. A few decisions shape the build before we start.

**Questions:**

1. When an event is **full**, do extra people get a waitlist, or is joining just closed?

- Recommendation: Should not be in initial version, but worth after.

2. Confirm paid events go through **Paymattic** (our plugin) — and which gateways to enable there (Stripe, PayPal…)?
3. Online meetings integration — **Google Meet and Zoom both**?
4. Do we need **recurring events** (repeat weekly/monthly) in v1, or later?

- Recommendation: yes in v1.

5. Google Places (location picker + map) needs an API key — is that set up / acceptable as a dependency?

- Recommendation: In next phase yes.

**Answer:**
_(team lead)_

**Affects:** [events/events.md](functional-requirement-parts/events/events.md)

---

## Q6 — Multiple calendars in v1?

**Context:** FR-8 allows many calendars per space. Simpler v1 = one default calendar; multi-calendar later.

**Questions:**

1. Ship **multi-calendar** in v1, or start with a **single default calendar** and add multi later?
2. If single at first: events still carry `calendar_id` (pointing to the one default calendar) so multi can turn on later with **no migration** — OK?

- Recommendation: Not in first phase (multi later). For v1, auto-create a hidden **"Default Calendar"** in the background when the first event is created — not shown in the UI. Every new event files under it. `calendar_id` is always set, so enabling multi-calendar later needs no migration.

**Answer:**
_(team lead)_

**Affects:** [calendar/calendar.md](functional-requirement-parts/calendar/calendar.md), [calendar/calendar.md](functional-requirement-parts/calendar/calendar.md)

---

## Q6 — Should we show notifications in v1?

**Context:** FR-9 allows to send notifications on different system.

**Questions:**

1. Send notification?

- Recommendation: Not in first phase (implement later).

2. Type of notifications?

- Recommendation: In app, email

**Answer:**
_(team lead)_

**Affects:** [notification/notification.md](functional-requirements/notifications/notifications.md), [notification/notification.md](functional-requirement/notifications/notifications.md)

---
