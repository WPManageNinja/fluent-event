# FR-10.1: Community-wide Events (FluentCommunity)

**Summary:** A site administrator can post an event to the whole community instead of a space.
In fcom terms it is a global post (`fcom_posts.space_id = NULL`, `content_type = 'event'`). Its
event lives in one auto-created **Community** calendar. Everything else (registration, the
3 lifecycle rules, visibility through the post) works as it does for space events
([fluentcommunity-integration.md](fluentcommunity-integration.md)).

Decided with the product owner on 2026-11-09. Status column: **Today** = already on `develop`,
**Branch** = done on `feat/community-events-without-space` before this spec, **Build** = built in
this change (2026-11-09; every row below is implemented and the tests listed at the end pass).

## Prerequisite

fcom's **Settings → General Settings → Global Post Status → "Enable Global Posts Status Form"**.
It is **off by default** (`disable_global_posts = 'yes'`). While it is off, no new community-wide
events can be posted (see A6).

## Terms

| Term | Meaning |
|---|---|
| Community-wide event | An event whose post has no space. UI word: **Community-wide**. |
| Space event | An event whose post is in a discussion space (shipped). |
| Community calendar | The one calendar that holds every community-wide event. |

## Data

| # | Rule | Status |
|---|---|---|
| D1 | A community-wide event's calendar is the **Community** calendar: title `Community` (translatable), slug `community` (`community-2` if taken), status `private`. | Branch |
| D2 | It is identified by `fe_calendars.meta.fcom_global = true` (no `fcom_space_id`). The lookup confirms the decoded meta in PHP, like the space lookup does. | Build (branch used `fcom_community`, raw `LIKE` only) |
| D3 | Created lazily on the first community-wide event, reused after. Two first posts at the same moment may create two; accepted, same as space calendars (PR #72). | Branch |
| D4 | No schema change, no migration (pre-release). | — |

## URLs

| Page | URL | Status |
|---|---|---|
| Space event | `/{portal}/space/{space-slug}/event/{event-slug}` | Today |
| Community-wide event | `/{portal}/event/{event-slug}` (mirrors fcom: `/post/{slug}` vs `/space/{s}/post/{slug}`) | Build (server path is Branch) |
| Its fcom post | `/{portal}/post/{post-slug}` (fcom's own) | Today |
| All Events | `/{portal}/events` | Today |

- **U1.** A URL whose space part doesn't match where the event lives now (moved event, wrong link)
  shows **"This event is not available."** No redirect. A space event opened at `/event/{slug}` is
  likewise not available. — Branch
- The landing pages (`/{portal_slug}/{event-slug}`, default `events`) are unrelated and unchanged.

## Who sees

| # | Rule | Status |
|---|---|---|
| W1 | Same as fcom global posts: every logged-in member; logged-out visitors only when the portal is open to visitors. | Branch |
| W2 | The usual extra conditions hold: post `published` (lists) or `published`/`unlisted` (page, registration); author's community profile active; event not `draft`. | Today |

## Views — portal

| # | View | Behaviour | Status |
|---|---|---|---|
| V1 | fcom home feed + author's profile | The post shows there with the event card (fcom's own feed). | Today |
| V2 | Event card in the feed | Title links to `/event/{event-slug}` (space events keep their space URL). | Build |
| V3 | All Events (`/events`) | Lists community-wide events with space events (tabs, search, views unchanged). | Branch |
| V4 | All Events — origin icon | Each **space** event shows its space's icon (logo image, else emoji, else SVG shape, else a generic icon) with the space name as tooltip and accessible label. Community-wide events show nothing. **Card and timeline views only**; not in a space's own tab, not in the calendar grid. No filter. | Build |
| V5 | A space's Events tab | Only that space's events; never community-wide ones. | Branch |
| V6 | Community-wide event page | Same page as a space event, rendered at portal top level (no space header/tabs). | Build |
| V7 | Back buttons | Every Back (event page, create page) is `router.back()`, no fixed fallback URL. | Build (event page fell back to the space tab) |

## Views — create page

| # | Rule | Status |
|---|---|---|
| C1 | First section: **"Where do you want to post this event?"** with two radio cards: **Community-wide** / **In a space**. | Build |
| C2 | **In a space** is always preselected. Opened with no space (feed home, All Events): no space picked yet in the composer's picker. | Build |
| C3 | Opened from a space's "…" menu (`?space=slug`): the choice still shows, **In a space** preselected with that space selected in the composer. | Build |
| C4 | fcom global posts off: the choice still shows, **In a space** preselected, **Community-wide** disabled with a note that global posts are off; hovering it shows the path to turn them on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form. | Build |
| C5 | Community-wide → fcom's composer posts to "On my profile" (`feed.space = '__self__post__'`, picker hidden with `disableSpace`; never through the composer's `space` prop, which hides the whole composer for a slug that isn't a real space). In a space → composer's space picker, spaces only. The caption is written in the composer either way. | Build |
| C6 | The **Feed post** section (fcom's composer, teleported in from the page shell) sits right after the choice, before the event details, folded ("What's happening, …") until clicked, as in the feed. The caption is optional for event posts (fcom's `fluent_community/feed/message_required` filter). Create Event first checks the event in the page (title, end after start, an online event's meeting link): a miss only shows an error toast, the composer stays as it was and nothing takes the focus; otherwise it opens a folded composer and posts, and the server validates as before. Hint: "What members see in the feed, above the event." | Build |
| C7 | After posting: published → fcom's post page (today). Scheduled/held → `router.back()`. | Build (held went to the space tab) |

## Views — admin and landing pages

| # | View | Behaviour | Status |
|---|---|---|---|
| A-V1 | Calendars list | The Community calendar sits in the **FluentCommunity** group. | Build |
| A-V2 | Calendar detail | FluentCommunity mark; tooltip **"This calendar holds community-wide events."**; no space link. | Build |
| A-V3 | Events list | The "view post" link (#287) points at `/post/{slug}`; fcom builds it. | Today |
| A-V4 | Landing pages | The Community calendar is `private` and fcom events are `private`, so nothing shows unless an admin changes it. | Today |

## Actions

| # | Action | Behaviour | Status |
|---|---|---|---|
| A1 | Create | **Site administrators only** (`manage_options`), as for every event in the free plugin. | Today |
| A2 | Create community-wide | Needs fcom global posts on; otherwise refused with a message. Event `private`, in the Community calendar. | Build (gate) / Branch (rest) |
| A3 | Edit the post | Event untouched, link kept. | Today |
| A4 | Move space → community-wide (fcom "move to profile") | Allowed; who may = fcom's own rule (space admins/moderators). Event + guests move to the Community calendar. | Branch |
| A5 | Move community-wide → space | Allowed into discussion spaces, fcom's rule. Event + guests move to that space's calendar. | Branch |
| A6 | fcom global posts **off** | New community-wide events and moves to profile are refused. Existing ones stay as they are. | Build |
| A7 | Delete the post | Event parked: `draft`, unlinked, guests kept. | Today |
| A8 | Delete the event (admin) | Post becomes a plain post. | Today |
| A9 | Admin moves an event into the Community calendar | Its post leaves its space and becomes community-wide (an admin choice; not blocked by the fcom setting). | Branch |
| A10 | Admin moves a community-wide event to a space calendar / a site calendar | Post moves to that space / becomes a plain post. | Branch / Today |
| A11 | Admin deletes the Community calendar | Allowed, like any calendar: its events are deleted, posts become plain; the next community-wide event creates a fresh calendar. | Today |
| A12 | Admin renames / changes status of the Community calendar | Allowed, nothing syncs. | Today |
| A13 | Register (members, visitors on `open`) | Same rules as space events; guest `source = 'fluent_community'`, `source_id` = post id. | Today |
| A14 | A space is deleted | Community-wide events are not affected. | Today |

## Out of scope

Course events, events-only spaces, non-admin creators (Pro permissions), redirecting moved-event
URLs, an origin filter on All Events. See the 2026-11-09 system design report.

## Tests

- PHP integration: create community-wide (calendar meta, reuse), refused when fcom global posts are
  off (create + move to profile), move space ↔ community-wide, admin calendar change into the
  Community calendar, list and by-slug visibility (member, visitor, space filter).
- JS unit: the `/event/:event_slug` route, card and list links for community-wide events, the
  create-page choice (defaults per C2–C4), Back = `router.back()`, the space icon (V4).
