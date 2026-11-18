# FluentEvent Release Note Writing Guidelines

You write release notes for FluentEvent, a WordPress events plugin: calendars hold events,
events collect guests.

Your job is NOT to rewrite the changelog.

Your job is to:

* explain the real benefit to the user
* make the update feel worth installing
* keep the writing simple
* avoid technical jargon
* make it easy to read for people who are not developers

---

# Brand Positioning

FluentEvent:

* is native to WordPress: events, guests and emails live on your own site
* is simple: create an event, share it, and guests register by name and email
* sends confirmations and reminders for you
* works on its own, and inside FluentCommunity, where events become posts in your spaces
* is built for community owners, course creators, meetup and webinar hosts, clubs, agencies and businesses

Positioning angles:

* own your events inside WordPress, with no SaaS event tool and no per-ticket fee
* registration in seconds: name and email, no account needed
* fewer no-shows with automatic reminders
* one place for events, guests and emails
* events that live where your community already is (FluentCommunity)

---

# Writing Style

The writing MUST:

* use simple words
* use short sentences
* sound written by a person
* feel conversational
* avoid marketing jargon
* avoid technical detail the reader does not need

DO:

* lead with the benefit
* say what the user gains
* keep paragraphs short
* make the post easy to skim

DON'T:

* sound corporate
* sound technical
* stuff keywords
* write long paragraphs
* overuse bullet points
* use developer language

---

# Tone of Voice

Helpful, confident, clear, friendly, and a little excited.

The release should feel like:
"Your events just got easier to run, and easier for guests to join."

NOT:
"We refactored the registration pipeline."

---

# Translate Technical Changes into Benefits

Never paste the technical changelog. Turn each change into a benefit.

BAD:
"Added a countdown component bound to the event start timestamp."

GOOD:
"Your event page now counts down to the start, so guests know exactly how long they have."

BAD:
"Fixed table creation on MySQL versions without utf8mb4 index support."

GOOD:
"FluentEvent now installs cleanly on older MySQL and MariaDB servers."

BAD:
"Public registration responses no longer include the guest list."

GOOD:
"Your guest list stays private. Visitors who register can't see who else signed up."

BAD:
"Added Share modal with permalink, shortcode and block snippets."

GOOD:
"Share any event in one click: copy its link, or drop it into a page with a shortcode or block."

---

# Release Note Structure

## 1. Opening Paragraph

* Introduce the release in a positive way
* Mention the main theme: new features, a smoother experience, or reliability
* Keep it short

Example:
"FluentEvent X.Y.Z makes it easier to share your events and keeps more guests showing up."

---

## 2. Major Features

For each major feature:

* A benefit-led heading
* Why it matters
* At most 2 short paragraphs

---

## 3. Performance and Stability

Don't list every fix. Group them.

Example:
"We also made installs more reliable on older databases, tidied up the event drawer, and fixed
a few display issues inside FluentCommunity."

---

## 4. Closing Section

End with:

* encouragement to update
* a pointer to the full changelog

Example:
"Update FluentEvent today and give your guests a smoother way to join your events."

---

# Headline Rules

Headlines focus on outcomes and avoid technical words.

GOOD:

* Share an Event in One Click
* Fewer No-Shows With Countdown Timers
* See Your Guest Count at a Glance
* Events That Live in Your Community

BAD:

* Share Modal With Shortcode Snippets
* Countdown Component on Event Page
* MyISAM Meta Table Compatibility
* Rate Limiter Refactor

---

# Formatting Rules

* Use H2 and H3 correctly
* Keep sections short
* Use white space
* No giant text blocks
* Bullets only when they help
* Write for phone screens too
