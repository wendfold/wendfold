# Wendfold roadmap

Current version: `1.0.0`

This is the public roadmap for Wendfold. It lists the work planned before and
after the first release and the ideas being considered. Plans can change. To
suggest a feature, open a feature request issue.

## What 1.0.0 includes

- One installer, `pkg_wendfold`, for the component (`com_wendfold`) and the
  user, task and privacy plugins
- Joomla update notifications from one package update server
- Member profiles, officers and officer history, dues payments, and member
  roster CSV import with configurable column mapping
- Events with event types, statuses, audiences, departure and return times,
  and saved locations, shown in card and list views with filters
- RSVPs, QR self check-in, road captain attendance, and meeting roll call
- Ride groups with per-group road captain and sweep, and rider assignment
- Overnight trips with itinerary stops and lodging status
- Volunteer roles, member sign-up, and organizer assignment and waitlists
- Participation metrics, member activity logging, and configurable awards
- Scheduled renewal and event reminders, and email blasts
- Joomla privacy export and removal support

## Before the first release

- Publish the update feed and the package download on wendfold.com and check
  update discovery on a clean Joomla 6 site
- Final documentation pass against the release package
- Submit the Joomla Extensions Directory (JED) listing
- Language file review: remove unused keys and keep wording consistent
- Regression pass on events, RSVPs, check-in, volunteers, meetings, trips,
  awards, and package install, update and uninstall
- Event audience checks across the events list and event pages

## Under consideration

These may be picked up if users ask for them:

- Mobile layout polish for the events list
- Remembering events list filters between visits
- More ride-group helpers for road captains
- A month calendar view for events

## Later

- Event photo galleries and photo uploads
- Online dues payment
- Self-registration tied to payment
- Posting events to social media
- A mobile app or progressive web app
- More communication automation
- Merchandise and patch sales

## Not planned

- Splitting the suite into separately published extensions. `pkg_wendfold`
  stays the only installer.
