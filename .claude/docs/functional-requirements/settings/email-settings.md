# FR-4.7: Email Settings — catalog & templates

Every system email is customizable (subject, body, on/off) and wrapped in a shared branded template. Parent: [settings.md](settings.md). Delivery via `wp_mail()` → FluentSMTP.

## Approach

- Per-email config (`{enabled, subject, body}`) in the `emails` row of `wp_fevents_meta`.
- Shared branded wrapper, configured once.
- Merge tags resolved at send: `{{user.name}}`, `{{event.title}}`, `{{event.date}}`, `{{code}}`, `{{action_url}}`, etc.
- Required emails (codes) can't be disabled; informational ones can.

## Email catalog

### Account & auth

| Email                | Trigger                | To          | FR         | Default  |
| -------------------- | ---------------------- | ----------- | ---------- | -------- |
| Verification code    | signup / verify        | new user    | FR-1.4     | required |
| Forgot-password code | reset requested        | user        | FR-1.5     | required |
| Email-change code    | change-email requested | new address | FR-2.3     | required |
| Password changed     | password updated       | user        | FR-2.2     | on       |
| Email changed alert  | email updated          | old address | FR-2.3     | on       |
| Welcome              | registration complete  | user        | FR-1.1/1.2 | on       |

### Events

| Email                 | Trigger                     | To                | FR        | Default      |
| --------------------- | --------------------------- | ----------------- | --------- | ------------ |
| Invitation            | host invites                | invitee           | FR-1.7    | on           |
| Join request received | member requests             | host/managers     | FR-1.6    | on           |
| Join request accepted | host accepts (+ login link) | joinee            | FR-1.6    | on           |
| Join request declined | host declines               | joinee            | FR-1.6    | optional     |
| New event announced   | event published             | members/followers | events    | optional     |
| Upcoming reminder     | before start                | attendees         | reminders | on           |
| Event updated         | details changed             | attendees         | events    | on           |
| Event cancelled       | cancelled                   | attendees         | events    | on           |
| Payment receipt       | paid registration           | attendee          | payments  | on (if paid) |

### Membership & roles

| Email                | Trigger                  | To     | FR     | Default  |
| -------------------- | ------------------------ | ------ | ------ | -------- |
| Member status change | suspend/block/reactivate | member | FR-3.5 | optional |
| Manager role changed | role assigned/removed    | user   | FR-4.4 | optional |

### Digest (later)

| Email                  | Trigger   | To          | FR    | Default |
| ---------------------- | --------- | ----------- | ----- | ------- |
| Upcoming-events digest | scheduled | subscribers | later | off     |
