# Play Store — Default store listing (en-US)

Copy-paste source for the Play Console "Default store listing" page.
Keep this file in sync whenever the listing changes.

---

## App name (30 max)

```
Haraan: Events & Sports
```

23/30 — already set in the console.

---

## Short description (80 max)

```
Book events and turf near you, and play gully cricket with live scores.
```

70/80.

---

## Full description (4000 max)

```
Haraan is one app for the two things you actually do on a free evening: go out, or go play.

Find concerts, comedy nights, workshops and sports events near you, book a turf or court for your game, and follow live gully cricket from your own ground — all in one place.

GO OUT — EVENTS & TICKETS
• Browse events near you, sorted by how close they actually are
• Concerts, comedy, workshops, food, culture and sports events
• Real seat and ticket tiers with live availability — no guesswork
• Apply coupons at checkout and pay by UPI or card
• Your ticket arrives by email and push notification, with a QR pass you can show at the gate
• Every event page carries the details you look for: timings, venue on a map, language, age limit, layout and organiser terms

GO PLAY — TURF & COURT BOOKING
• Discover turfs and courts near you across cricket, football, badminton, tennis and more
• See prices, timings, amenities, photos and real reviews before you book
• Pick your slot and confirm — your booking sits in one place with your event tickets

GULLY CRICKET, PROPERLY SCORED
• Create a match, add your squads, name your captain and vice-captain
• Ball-by-ball scoring built for gully rules, not just the pro format
• Live scorecard your friends and family can follow while the match is on
• Match feed, leaderboards and a player profile that grows with every game you play

BUILT FOR HOW YOU USE YOUR PHONE
• Sign in with your phone number, Google account or email
• English, हिन्दी, తెలుగు, தமிழ், ಕನ್ನಡ and മലയാളം
• Your city and location drive what you see, so the app is useful the moment it opens
• Notifications inbox and in-app support if you need a hand with a booking

ORGANISING SOMETHING?
Hosts and venue owners run their events, ticket types, coupons, desk sales and check-ins from the Haraan partner console at haraan.app.

Questions or feedback: haraan.app
```

---

## Assets

| Asset | Spec | Status |
| --- | --- | --- |
| App icon | 512×512 PNG | `store/ic_launcher_playstore.png` |
| Feature graphic | 1024×500 PNG | `store/feature-graphic.png` |
| Phone screenshots | 7 × 1080×1920 (9:16) | `store/screenshots/phone/` |
| 7-inch tablet screenshots | 5 × 1080×1920 (9:16) | `store/screenshots/tablet-7in/` |
| 10-inch tablet screenshots | 5 × 1440×2560 (9:16) | `store/screenshots/tablet-10in/` |
| Chromebook screenshots | 5 × 1920×1080 (16:9) | `store/screenshots/chromebook/` |
| Video | optional YouTube URL | none |

Both graphics are generated — see `store/icon-source/make_icon.py` and
`store/icon-source/make_feature_graphic.py`. Don't hand-edit them.

Screenshots are real captures of the app against production, taken from a
scripted emulator session — see `store/screenshot-source/README.md` to
reproduce. Upload them in filename order; the phone set runs:

1. `01-discover-events` — Events feed, nearby, with the For You carousel
2. `02-event-details` — event page: poster, date/venue/time, price bar
3. `03-select-tickets` — ticket sheet with a tier selected and the total
4. `04-gamehub` — GameHub: live ActionBoard card, sport filters, venues
5. `05-venue-details` — venue page: photos, rating, hours, ₹/hr, Book Now
6. `06-live-matches` — ActionBoard live matches near you
7. `07-live-scorecard` — live match: hero score, commentary, batting card

Note the greeting reads "Hey there!" because the captures run as a guest —
phone-OTP sign-in can't be scripted. Retake with a signed-in session if you
want a personalised greeting and the Tickets lane in shot.
