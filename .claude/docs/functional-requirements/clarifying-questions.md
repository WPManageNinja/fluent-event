# Clarifying Questions — for the Team Lead

> Open questions that need a decision before the related spec can be finalized. Each has context + the decision needed. Lead fills in **Answer**. Once answered, update the file(s) listed under **Affects**.

---

## Q1 — Is there an "all members" page (member directory)?

**Resolved — obsolete after the email-only pivot.** There are no members: attendees are **anonymous email guests** (no accounts) and hosts/managers are **WP users** managed in wp-admin. FR-3 Member Management was removed, so there is no member list or public directory. Per-event **guest lists** (managers-only, FR-6.8) cover any list need.

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

**Resolved — obsolete.** FR-5 Activity Tracking (the `wp_fe_activities` audit log) was removed from scope. There is no activity/audit log. This question no longer applies.

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

6. Keep event-guest-level settings (e.g. per-event notification preference)?

- Recommendation: Not in v1. Global per-user prefs (FR-9.3) are the control. Add the per-event override (tri-state per-channel, `event > calendar > global`) later, only if "mute/override this event" is actually requested.

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

## Q7 — Event-space deletion (fcom integration)

**Context:** FR-10 binds an fcom `event` space 1:1 to a Fluent Events calendar (auto-created with the space). When the host deletes the event-space, what happens to the bound calendar + its events?

**Questions:**

1. Cascade — soft-delete + cancel the bound calendar and its events (FR-6 cancel flow, notifies guests)?
2. Or unbind — the calendar (and events) survive as a standalone calendar in the fevents portal; only the space goes away?

- Recommendation: Cascade (option 1). The space and its calendar are 1:1 and created together, so deleting the space should cancel the calendar + events with the FR-6 cancellation notifications. Offer "unbind" only if hosts need to keep a calendar after removing its community space.

**Answer:**
_(team lead)_

**Affects:** [fluentcommunity-integration/fluentcommunity-integration.md](functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md)

---

## Q8 — Identity & audience when both plugins are active (fcom integration)

**Context:** FR-10.5. Fluent Events has no profile table (attendees are anonymous email guests; hosts/managers are WP users). With FluentCommunity active, fcom's XProfile supplies community identity data, keyed off `wp_users.ID`. Need to confirm the canonical identity and how a linked calendar's notification audience is resolved.

**Questions:**

1. Identity source when both are active — fcom canonical (reuse fcom users / XProfile / space members)? Or independent (only the WP user shared)?

- Recommendation: fcom canonical — one source, no drift. Identity is always `wp_users.ID`; Fluent Events keeps no profile table of its own in either mode.

2. Notification audience for a linked calendar — the fcom space's members?

- Resolved: Yes. Space members are the audience for a linked calendar's event notifications. There is no calendar follow/subscribe concept (the `wp_fe_calendar_followers` table was dropped); standalone calendars have no subscriber list, so their event notifications reach the event's guests only (FR-9).

**Answer:**
_(team lead)_

**Affects:** [fluentcommunity-integration/fluentcommunity-integration.md](functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md) (FR-10.5)

---

## Q9 — Notify both Fluent Events and fcom? (fcom integration)

**Context:** FR-10.6. When a notification fires for an event whose parent calendar is fcom-linked, we can deliver it to Fluent Events' own notifications **and** fcom's notifications. Risk: the same member is notified twice (Fluent Events bell + fcom bell).

**Questions:**

1. For a linked calendar, deliver the in-app notification to both Fluent Events and fcom, only fcom (fcom is the canonical identity in integrated mode), or only Fluent Events?

- Recommendation: fcom only when integrated — one bell, no duplicates. Make "both" a setting only if hosts ask.

2. Email — sent once regardless of the in-app choice (FR-9)?

- Recommendation: Yes, once.

**Answer:**
_(team lead)_

**Affects:** [fluentcommunity-integration/fluentcommunity-integration.md](functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md) (FR-10.6)

---

## Q10 — Where is the "post to feed" control? (fcom integration)

**Context:** FR-10.7. An event space can post an announcement to the community feed when an event publishes. Where should that control live?

**Questions:**

1. Set it at the **community/space level** (one toggle on the event space → applies to every event), at **event-creation time** (a per-event toggle each time), or both (space default + per-event override)?

- Recommendation: space-level default + optional per-event override. If only one, pick space-level — less friction, and the host decides the space's behavior once.

**Answer:**
_(team lead)_

**Affects:** [fluentcommunity-integration/fluentcommunity-integration.md](functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md) (FR-10.7)

---
