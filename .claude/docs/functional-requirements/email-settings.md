# FR-4.7: Email Settings — catalog & templates

Every system email is customizable (subject, body, on/off) and wrapped in a shared branded template. Parent: [settings.md](settings.md). Delivery via `wp_mail()` → FluentSMTP.

## Approach

- Per-email config (`{enabled, subject, body}`) in the `emails` row of `wp_fevent_meta`.
- Shared branded wrapper, configured once.
- Merge tags resolved at send: `{{guest.name}}`, `{{event.title}}`, `{{event.date}}`, `{{action_url}}`, etc.
- Transactional emails (payment receipt) can't be disabled; informational ones can.

## Email catalog

### Events

| Email                 | Trigger              | To            | FR        | Default      |
| --------------------- | -------------------- | ------------- | --------- | ------------ |
| Invitation            | host invites         | invitee       | FR-6.7    | on           |
| Join request received | visitor requests     | host/managers | FR-6.9    | on           |
| Join request accepted | host accepts         | joinee        | FR-6.9    | on           |
| Join request declined | host declines        | joinee        | FR-6.9    | optional     |
| Upcoming reminder     | before start         | attendees     | reminders | on           |
| Event updated         | details changed      | attendees     | events    | on           |
| Event cancelled       | cancelled            | attendees     | events    | on           |
| Payment receipt       | paid registration    | attendee      | payments  | on (if paid) |

### Managers

| Email                | Trigger               | To   | FR     | Default  |
| -------------------- | --------------------- | ---- | ------ | -------- |
| Manager role changed | role assigned/removed | user | FR-4.4 | optional |

### Digest (later)

| Email                  | Trigger   | To          | FR    | Default |
| ---------------------- | --------- | ----------- | ----- | ------- |
| Upcoming-events digest | scheduled | subscribers | later | off     |
