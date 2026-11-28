# Baggr: product spec

_Draft v0.22, 28 Nov 2026. This document covers the **what** and **why**. Claude Code checks each section against the codebase before it becomes an issue._

---

## 1. What Baggr is

Baggr is a private web app for small groups of friends who climb Scotland's hills together: the 282 Munros first, and the other hill lists too (§3). It answers six questions:

1. **Which hills have I done, and with whom?**
2. **Which hills do I still need, and where's the weather best?**
3. **When can we all go, and what's the plan?**
4. **What did we actually do?**
5. **How has my bagging gone over the years?**
6. **Are they all right?** (check-ins for the people at home)

### Principles

- **Phone first.** Most use happens on a phone: in the pub, in the car park, at the summit. Every screen must work one-handed on a small screen. Desktop is a bonus.
- **Link out, don't copy.** Walkhighlands and MWIS are linked to, not copied. When Baggr shows MWIS data, it shows a few short extracted values and always links to the full forecast.
- **Small and private.** Baggr is for invited groups of friends. It isn't a public social network, and it doesn't need scale features.
- **Easy to record.** Logging a hill should take two taps. If recording is a chore, the history won't get kept.
- **Keep it light.** Storing group photos is out (too intensive).
- **One name, one thing.** Every concept has one name, used the same way in the app, the spec and the code, and no two concepts share a name. The glossary in §3 is the authority.

### Look and feel
Baggr gets its own identity, replacing the Laravel defaults.

- **Name:** always written in lower case, **baggr**.
- **Hill mark (chosen: direction A, "Peak"):** an original drawing of a solid peak with a lower shoulder, as a filled silhouette. There's no cairn or anything else on the summit. It's drawn as a single-colour SVG so it stays crisp at any size. It isn't traced from anyone's photo or logo, and it isn't based on a specific copyrighted image. The options and the chosen version are on the design canvas "baggr logo and icon options".
- **Wordmark:** "baggr" in a bold, rounded sans-serif font.
- **Lock-up:** the hill mark above or beside the wordmark, used on the landing page and at the top of the app's sidebar. Side by side, the **foot of the mountain sits on the wordmark's baseline**, and the mark is 0.75 × the font size tall, so the peak reaches about the top of the "b".
- **Colours:** two colours plus neutrals: hill green `#1F4D3A` and heather `#C9B5DD` (on dark), each with a dark-mode version. Keep them as design tokens so they're easy to adjust. The same two colours become the defaults for group and solo colours (§2).
- **App icon:** the hill mark alone, in a light colour on a solid brand-colour square. At very small sizes (16–32px) the peak is drawn with a deeper notch at the shoulder, so it still reads as a hill rather than a plain triangle in a browser tab.
  - `favicon.svg`, plus `favicon.ico` containing 16, 32 and 48px sizes
  - `apple-touch-icon.png` at 180px for iPhone home screens
  - 192px and 512px PNGs, plus a 512px "maskable" version with the mark inside the safe zone, for Android and add-to-home-screen (§12)
- **Files:** the exported mark, lock-ups, favicons, app icons and a standalone landing page mock-up are in `docs/brand/`, with a README saying where each one goes.
- **Replaces:** the Laravel logo in `x-app-logo` and `x-app-logo-icon`, the existing `favicon.ico`, `favicon.svg` and `apple-touch-icon.png`, and the welcome page. `APP_NAME` becomes `baggr`.

---

## Priorities (MoSCoW, for version 1)

- **Must:** version 1 isn't usable without it.
- **Should:** important, and planned for version 1, but Baggr still works without it.
- **Could:** nice to have if time allows.
- **Won't (this time):** deliberately left out of version 1, but could come back later.
- **Never:** ruled out permanently, as a matter of principle.

| Feature | Section | Priority |
| --- | --- | --- |
| Home page (plain signed-out landing page; signed-in dashboard) | §6.0 | **Must** |
| Look and feel: hill mark, baggr wordmark, app icons | §1 | **Must** |
| Whole app behind sign-in; public registration off | §6.0 | **Must** |
| Groups, invitations, members and guests | §2 | **Must** |
| Alert admins when a Visitor is waiting | §2 | **Must** |
| Walks with days and hills in order | §8, §8.1 | **Must** |
| Logging: quick hill, round, trip | §5 | **Must** |
| Editing a logged walk, with dragging to reorder its hills | §5, §8.1 | **Must** |
| Hill list: grouping by MWIS area or Murray section, list column, MWIS badge, filters | §3 | **Must** |
| MWIS fetch at 07:00 and 18:00, target day, rating | §4 | **Must** |
| Area view ("where's best this weekend?") | §4 | **Must** |
| My completed hills: X / total and by-section breakdown | §6.1 | **Must** |
| My hills to do: list with forecast | §6.2 | **Must** |
| Plan lifecycle: draft, plan, postpone, cancel, close into ascents | §8 | **Must** |
| Date polls on a draft plan | §7 | **Must** |
| ntfy notifications for the group | §10 | **Must** |
| GPX routes: upload, draw on a map, download | §9 | **Must** |
| Other hill lists (the tops, Corbetts, Grahams, Donalds) | §3 | **Must** |
| Maps of completed hills and hills to do | §6.1, §6.2 | Should |
| History timeline | §6.3 | Should |
| Open-Meteo outlook beyond MWIS's range | §4 | Should |
| Check-ins for the people at home: open share link, check-ins with position and a note, followers' ntfy | §11 | Should |
| OwnTracks: moving dot and trail on the share page, "Reached [hill]" entries | §11 | Should |
| Expected back time and "no finish yet" notice | §11 | Should |
| Money link (Splitwise or Monzo) | §8 | Should |
| "Needed by the most people" hill suggestions | §12 | Should |
| Daylight hours for plan days | §12 | Should |
| Add to home screen (PWA) | §12 | Should |
| Direction setting that reorders hills automatically | §8.1 | Could |
| GPX: spotting the Munros a route passes; elevation profile | §9 | Could |
| Saving the tracked trail as a route | §11 | Could |
| Compleation tracker (projected finish) | §6.1 | Could |
| Hill photos from Geograph | §15 | Could |
| Import of past ascents from Excel (one-off) | §5 | **Must** |
| Lift sharing | §12 | Could |
| Calendar feed (.ics) | §12 | Could |
| Safety/route card | §12 | Could |
| Seasonal notices (stalking, avalanche) | §12 | Could |
| Group photos and photo uploads | §13 | Won't |
| Tracking costs inside Baggr | §13 | Won't |
| Public profiles and social features | §13 | **Never** |
| Weather sources other than MWIS and Open-Meteo | §13 | Won't |
| Satellite trackers (Garmin inReach) | §11 | Won't |
| Copying or storing Walkhighlands content (text, photos, route descriptions) | §13 | **Never** |

---

## 2. People and groups

- Each person has an account (the Laravel/Fortify starter kit already provides this).
- A person can belong to **several groups**, for example "MGB" and a work hillwalking club.
- Each group has a **name** and a **colour**. The colour is used on maps and timelines to show who a hill was done with.
- Each person also has their **own colour**, used for hills done solo.
- **Any signed-in member can create a group** and becomes its admin.
- Joining a group is **by invitation only**, and there's no open sign-up. There are two ways in, and creating an account through one of them is the only way to register, because public registration stays off.
- **Roles.** A person has one role in each group:
  - **Admin:** manages members and the group's link, and can do everything a member can.
  - **Member:** plans, logs and votes. This is the ordinary walker.
  - **Follower:** for spouses and loved ones at home. A follower can **change nothing about the group**. They see all of the group's walks, and can comment on a walk's check-in page (§11).
  - **Visitor:** someone who has just arrived through the group's shared link. A visitor sees only the group's name and "waiting for an admin to approve you", until an admin promotes them. **Visitor can't be chosen from the front end**: an admin picks Admin, Member or Follower, and Visitor is only what the link produces.
- **Invite by email (way one).** An admin types an email address on the group page and picks the role, Admin, Member or Follower. The admin has vouched for this person, so they go straight in at that role.
  - If the email belongs to an **existing account**, that person is invited and **accepts** from an email and from their home page before they're added.
  - If it doesn't, Baggr sends an **invitation email** with a link made for that address alone. It works once, **expires after 7 days**, and an admin can cancel it. Opening it lets the person create an account (a name and a password), and the link in the email proves they own the address, so they don't need a second verification email.
- **The group's reusable link (way two).** One link per group, **shared by message**, usually on Signal or WhatsApp. It **expires after 7 days**, and an admin can revoke it or make a new one. Opening it only shows the group's name and a **Join** button, so a link preview can't join anyone. A person with an account signs in and presses Join; a person without one creates an account from the page. Either way they arrive as a **Visitor**.
  - **Guards, because a forwarded link is the main risk:** joining through the link is rate-limited, a group holds **at most 30 waiting Visitors**, and the link page shows nothing beyond the group's name. An admin sees each Visitor's name and email before promoting them.
  - **Admins are told.** When a Visitor arrives through the link, every Admin of the group is **emailed** (through Resend) with the person's name and email address, the group's name and a link to the group page. The email approves nothing: the admin signs in and promotes the person there. One email goes out per arrival: opening the link again or pressing Join twice sends no second one, and nothing is sent if the person is already a member. It is email, not ntfy, because an admin may not have subscribed to the group's ntfy topic, and the ntfy alerts come later (§10).
  - **Seen at a glance.** A group an admin runs shows a small **"N waiting"** badge on the Groups list, so waiting Visitors don't need the group page opening to be noticed.
- **Email must be verified.** An account created any other way than an emailed invitation is sent a verification email (through Resend) and can't use Baggr until the link in it is opened. Accounts made by hand with `php artisan users:create` are already verified.
- Polls, plans and notifications all belong to **one group**.
- **Ascents belong to the person.** Leaving a group doesn't touch someone's own history, and the group keeps its walks. Deleting an account removes that person's ascents, and the group's walks then show them as a "former member".
- A walk can include **guests** who have no account. A guest is a name only, has no ascent history and **can't vote in polls**. Members add guests to a plan.
- An admin can change anyone's role (to Admin, Member or Follower) or remove them, **including another admin**. A group always keeps at least one admin, so the last admin can't be removed or demoted, and can't leave until someone else is made admin. Whoever creates a plan is its **organiser**. **Any attendee** of a plan can edit it, confirm its date and close it, not just the organiser.

---

## 3. Hills and areas

### Already built
A list of all 282 Munros and a page for each hill. Each hill has a Walkhighlands link, its MWIS area, grid reference, lat/long, Murray section and Murray sub-section.

### Hill lists
Baggr covers more than the Munros. There are nine lists, each a DoBIH class (counts are from DoBIH v18.6):
- **Munros** (282) and **Munro Tops** (226)
- **Corbetts** (222) and **Corbett Tops** (453)
- **Grahams** (231) and **Graham Tops** (844)
- **Donalds** (89) and **Donald Tops** (52)
- **Marilyns** (2,007, across Britain and Ireland, including the Ochils and the Fife Marilyns West and East Lomond)

The list of lists is confirmed: Marilyns are in (added in v0.9); Furths are not. The other DoBIH classes are stored with each hill but aren't shown as lists.

- A "Tops" list holds only the subsidiary tops, never the main hills. In DoBIH no hill is in both a main class and its tops class, so Munros and Munro Tops are two separate lists of 282 and 226. A hill can still be on lists of different kinds; for example, a Munro can also be a Corbett Top or a Graham.
- Totals are **counted from the data**, never hard-coded, because the lists get revised.
- **Data source:** the Database of British and Irish Hills (DoBIH), the same family of data as the current Munro table. DoBIH is licensed under Creative Commons Attribution 4.0 International (checked 17 Nov 2026 at www.hill-bagging.co.uk/dobih/downloads), so it can be imported and reused with a credit.
- **Credit:** every page that shows hill data carries a small credit: "Hill data: Database of British and Irish Hills v{version}, CC BY 4.0", linked to www.hill-bagging.co.uk/dobih. The version comes from the file imported. It goes on the hill pages and the landing page footer, and is added as soon as possible, because the Munro data already uses DoBIH numbers.
- **Import the whole database.** All of DoBIH v18.6 is imported, not just the five lists: 21,576 hills (12,422 in Scotland, plus England, Wales, Ireland, the Isle of Man and the Channel Islands), their alternate names, all 66 classifications, all 1,218 areas and the OS map sheets. Scotland is the default view; the other countries are there too, and get an MWIS forecast where MWIS has an area for them. The importer matches hills by DoBIH number, so the 282 Munros already in Baggr keep their Walkhighlands link, MWIS area and photo.
- **Areas.** DoBIH areas come in several kinds, shown by the first letter of the area code. Every kind is kept, so a walk can be placed in any of them; the glossary below names them. A hill can be in several areas. Hills with no tracked list, such as most of the Ochils, are still ordinary hills that can be picked, planned and logged. They just don't count towards a list total.
- **Not copied from the Access file.** The `.mdb` can't be read on the live host. The importer runs locally and the result reaches the live site as a database dump, as the first deployment did.
- **Linking out:** Walkhighlands has pages for Corbetts, Grahams and Donalds too, so the same slug-matching approach applies. The link is optional, because many hills have no page.
- **MWIS areas:** built in step 3, before weather. A mapping table assigns each DoBIH area to an MWIS area, and a hill inherits its MWIS area from it. A hill's own curated MWIS area, as the 282 Munros have, always wins over the mapping. MWIS has ten areas: five Scottish (Northwest Highlands, West Highlands, Cairngorms NP and Monadhliath, Southeastern Highlands, Southern Uplands) and five English and Welsh (Lake District, Yorkshire Dales and North Pennines, Peak District, Snowdonia National Park, Brecon Beacons). The Ochils map to Southeastern Highlands (SH), as MWIS's own area description says. MWIS's own descriptions place Mull and Arran in the West Highlands, and the Cheviots in the Southern Uplands, which MWIS describes as the Galloway hills east to the Lammermuirs plus the Cheviots. The English and Welsh areas follow the national parks, with Snowdonia covering the north of Wales from Pumlumon northwards and the Brecon Beacons the south. Skye is not named on MWIS's site but its forecasts discuss it under the Northwest Highlands.
- **How a hill's MWIS area is worked out** (built in step 3, `php artisan hills:map-mwis`, stored in `hills.mwis_area` and `areas.mwis_area`), in this order: the area MWIS already forecasts for it (the Munros); its DoBIH named area (the Lake District fells, the Welsh ranges, the Cheviots, the Peak District); its Murray section; or, for the six sections that straddle two MWIS areas, the area of the nearest Munro. The boundaries are close, not exact, because MWIS publishes no boundary lines. **Hills with no MWIS area** show no badge and no forecast: Ireland, the Isle of Man, Shetland, Orkney, the Western Isles, the north east of Scotland (Tomintoul to the Dee valley), and England outside the Lake District, the Dales and North Pennines, the Peak District and the Cheviots.
- **Choosing a list:** every hill view, stats page and history has a list selector. Munros is the default, and the choice is remembered.
- **Photos:** every hill gets a Geograph photo (CC BY-SA, credited), except hills that are only on one of the four Tops lists, which show none (a hill on a Tops list that is also on a main list, such as a Corbett that is a Munro Top, still gets one). They're fetched locally in batches at one request a second (about six hours in all, resumable) and reach the live site in the database dump.
- **Hills MWIS doesn't claim.** Where a hill's area is mapped to an MWIS area but MWIS doesn't say it covers that hill, for example West and East Lomond in Fife, Baggr shows that area's forecast labelled as the **nearest area**. Which hills those are follows MWIS's own area descriptions (read 28 Nov 2026, listed in `config/mwis.php`): the Central Belt hills other than the Ochils and the Sidlaws; Islay, Jura, Colonsay, Coll, Tiree, Canna, Rum and Eigg; Ayr to the Clyde; and English hills round, but outside, the named national parks and ranges.

### Glossary: one name, one thing
These are the only names for these things, in the app, the spec and the code.

| Name | What it is | Example |
| --- | --- | --- |
| **Country** | Scotland, England, Wales, Ireland, the Isle of Man, the Channel Islands | Scotland |
| **Area** | Any DoBIH place grouping a hill can be in; a hill can be in several | Ochil Hills |
| **Area category** | The kind of area, picked first. Each has the DoBIH code letter in brackets. | Hill range |
| **Hill range** (D) | A named range of hills, DoBIH's "region". Southern Scotland only. | Ochil Hills, Galloway Hills, The Cheviots |
| **Murray section** | One of the numbered sections of Munro's Tables, the number DoBIH gives every hill | 4. Fort William to Loch Ericht |
| **Murray sub-section** (M) | A section's lettered part. A section that isn't split (Shetland, Orkney, St Kilda, the Isle of Man and several in England) is its own sub-section. | 04B. Loch Treig to Loch Ericht |
| **RHB section** (R) | DoBIH's own Relative Hills of Britain section. Not the Murray sub-section. | HC04 |
| **Island** (I), **County** (C), **Historic county** (A), **Council area** (U), **London borough** | As DoBIH gives them | Highland |
| **Named area** (N) | A named upland or national park, DoBIH's own | Lake District, Dartmoor, Snowdonia |
| **MWIS area** | One of MWIS's ten forecast areas, always written in full. It comes from the mapping below, not from DoBIH. | West Highlands (WH) |
| **Section** | A heading row in a list grouped by MWIS area, Murray section or Murray sub-section | |
| **List** | One of the nine hill lists | Munro Top |
| **Walk** | An outing with its days, hills and people, whatever its status | A weekend in Knoydart |
| **Plan** | A walk that isn't completed yet (draft, polling, planned or postponed). It isn't a separate thing. | Next month's draft |

A hill's Murray section and sub-section are worked out from DoBIH's section for it ("4.2" is section 4, sub-section B), so they exist for every hill and no copy is stored. The word "region" is not used for anything.

### The three groupings
Wherever hills are put into sections, the user picks one of three, and the choice is remembered:

- **MWIS area**: NW, WH, EH, SH, SU, plus the five English and Welsh areas. This is what weather is based on. Hills with none go under "No MWIS area".
- **Murray section**, for example 4. Fort William to Loch Ericht.
- **Murray sub-section**, for example 04B. Loch Treig to Loch Ericht.

DoBIH gives every hill a Murray section and sub-section, so both work for every list, not just the Munros.

### Changes to the hill list
- **Choose a list** (Munros by default).
- **Choose an area:** first an **area category** (hill range, Murray sub-section, council area and so on), then one area in it, shown with the number of hills in it. Any DoBIH area (§3, Areas) can be picked, and the list narrows to the hills in it. With a list chosen as well (say Marilyns) it shows only that list's hills in the area; with no list it shows every hill in the area, whatever its category. Only hills on a list count towards a total. The same area picker is on My hills and the to-do list.
- **Group by** MWIS area, Murray section or Murray sub-section, or not grouped (the default). The hills are shown in section order with a heading for each section, in the accent colour (hill green, heather in dark mode), and the hills under it are indented. The grouping chosen is the one My hills opens with.
- **Columns:** name, height, MWIS area and list. There is no country or region column: the grouping and the area show where a hill is.
- **List column:** the most significant of the nine lists the hill is on, in the order Munro, Munro Top, Corbett, Corbett Top, Graham, Graham Top, Donald, Donald Top, Marilyn (a Munro that is also a Marilyn shows "Munro"). It is blank for a hill on none of them. It shows the full name, or on a phone the short code (M, MT, Ma), and sorts by significance, Munros first.
- **MWIS area on every hill**, in the list and on the hill's page, as a badge linking to that area's forecast, for signed-in people (§4).
- **Search** matches the start of a word in a hill's name or other names, so "Dum" finds Dumyat. DoBIH area names and MWIS area names are matched too, but only from four letters, so a short term doesn't bring in every hill in a council area.
- **Filters:** _Not done by me_, _Not done by anyone in [group]_ (for each group the person is in), _Done by me_, and height from and to. The done filters are for signed-in people; visitors don't see them.
- **Weather on the list:** each hill shows its MWIS area's forecast for the target day (see §4).

---

## 4. Weather (MWIS)

### What MWIS provides
- **Areas:** MWIS publishes a forecast for each of its ten areas every day: five Scottish (Northwest Highlands, West Highlands, Cairngorms NP and Monadhliath, Southeastern Highlands, Southern Uplands) and five English and Welsh (Lake District, Yorkshire Dales and North Pennines, Peak District, Snowdonia National Park, Brecon Beacons). The main issue comes out in the **afternoon**: MWIS says roughly 4:30pm, with updates whenever necessary, and it has been seen as early as 2:30pm. Each issue covers **3 days**.
- **Fields in each day's forecast:** headline, how windy, effect of wind, how wet, cloud on the hills, chance of cloud-free summits, sunshine and air clarity, how cold (at 900m), and the freezing level.

### The switch (v0.22)
Forecasts are **off by default** (`MWIS_FORECASTS`) and stay off on the live site until MWIS gives permission. While they are off, Baggr fetches nothing from MWIS and shows only areas and links to the forecasts. No text from MWIS is kept in the code repository: the tests use made-up forecasts.

### Fetching
- **A job at 07:00 and 18:00** (v0.22) fetches the latest forecast for all ten areas and stores it in the database. That's 20 requests a day, ten each time, and visitors never cause a request to MWIS. On the live site it runs through Laravel's scheduler, triggered by a cPanel cron job.
- The 18:00 run picks up that afternoon's issue, so from the evening Baggr has **the next 3 days**, and still holds today's. The 07:00 run picks up any change MWIS has made since, before people set off, and covers for a failed run the evening before. A later change isn't picked up until the next run; the link to the full forecast has it.
- If a fetch fails or the page layout changes, Baggr keeps showing the last good forecast, marked with its issue date, and logs the error. It never shows a blank.
- **Past forecasts are kept** (v0.22): one row for each area and date. A newer issue updates the row while MWIS still covers the date; after that it stays as it is and is never deleted. So the row kept is the last one fetched, normally that morning's. Earlier versions aren't kept.
- Baggr names itself to MWIS: `Mozilla/5.0 (compatible; Baggr/1.0; +https://baggr.co.uk)`.

### The target day
The **target day** is the **coming Saturday if it's covered, otherwise the furthest-out day that is**. The day's name is always shown, so it's clear which day you're looking at. When the day shown isn't the Saturday, the forecast has an **amber background** and a separate **Forecast date** field saying which day it is for (v0.22).

| From the evening of | Days fetched | Target day |
| --- | --- | --- |
| Mon | Tue, Wed, Thu | Thu |
| Tue | Wed, Thu, Fri | Fri |
| Wed | Thu, Fri, Sat | **Sat** |
| Thu | Fri, Sat, Sun | **Sat** |
| Fri | Sat, Sun, Mon | **Sat** |
| Sat | Sun, Mon, Tue | **Sat** (today's, still held) |
| Sun | Mon, Tue, Wed | Wed |

Before 18:00 each day shows the previous day's issue.

**Plans** use their own dates. A multi-day plan shows each day's forecast as soon as that day is covered.

### Beyond MWIS's range: the outlook
Plans and date polls often look further ahead than MWIS's 3 days. For those dates Baggr shows an **outlook** from **Open-Meteo**, a free forecast service for non-commercial use that needs no API key and covers up to about 16 days.

- **When:** for plan days and date-poll options more than 2 days away and up to 14 days away.
- **Where:** a point forecast for each hill on the plan, at the summit's coordinates and height. MWIS forecasts by area, but this one is per hill.
- **What Baggr shows:** wind at summit height in mph, low-cloud cover as a percentage (a rough guide to whether the tops are clear), and precipitation.
- **Rating:** the same Go / Marginal / Poor rules, using wind and treating "100 minus low-cloud %" as the cloud-free figure, for hills in Scotland only. It's always labelled **Outlook**, never just a rating, so it's clearly less certain. Hills outside Scotland show the outlook values with no rating.
- **Hand-over:** once a date comes within MWIS's range, the MWIS forecast replaces the outlook.
- **Fetching:** in the same job, only for hills on active plans and polls. That's a handful of requests a day.
- **Attribution:** Open-Meteo's data is CC BY 4.0, so every outlook display credits Open-Meteo.

### What Baggr extracts for each area and day
| Field | How MWIS gives it | What Baggr stores and shows |
| --- | --- | --- |
| Headline | Short text, on the **first day of each issue only** | The text, where there is one |
| Wind ("How windy? On the Munros") | Text with mph ranges, e.g. "40 to 55mph, occasionally 60mph, gusts…" | **Top of the main range** (55 here) as a number, plus the full text. Gusts are shown but not used in the rating. |
| Wind range | Taken from the same text | The main range ("40 to 55 mph"), shown on its own above the full text |
| Rain ("How wet?") | Description only, with no mm or percentage | MWIS's own one-line summary. **Not used in the rating**, because it's too subjective to score. |
| Chance of cloud-free Munros | Percentages, often varying across the area, e.g. "10% around Skye rising to 30% inland" | **Highest figure** (30 here) for the rating, plus the full range |
| Freezing level | Metres or a range, or "above the summits" | The text (matters in winter) |

If a number can't be extracted, Baggr stores the text, gives no rating for that area and day, and shows "See MWIS".

### Rating
A simple **Go / Marginal / Poor** rating per area and day, from the wind and cloud-free figures above. **It applies to the five Scottish areas only**, because the thresholds are tuned for Scottish Munros. The five English and Welsh areas get no rating: Baggr shows their headline, wind and other extracted text, and links to the full forecast.

| Rating | Rule |
| --- | --- |
| **Poor** (no go) | cloud-free chance **under 40%**, or wind **over 40mph** |
| **Marginal** | wind **30–40mph** |
| **Go** | everything else |

Rain never changes the rating. The thresholds are stored in config so they can be tuned later.

### Rules
- Every weather display links to the full MWIS forecast for that area.
- **Signed-in people only** (v0.21). The hill list and hill pages are public, but nothing from MWIS shows to a visitor who isn't signed in: no logo or forecast link, no MWIS area on a hill or in the list, no grouping by MWIS area, no search by an MWIS area's name, and later no weather.
- **Open question:** MWIS forecasts are copyright. Before Baggr shows extracted values, ask MWIS whether that's OK. If they say no, Baggr falls back to showing only the area plus a link.

### Area view
A page listing the five Scottish MWIS areas for the target day, then the five English and Welsh ones. Each area shows:
- its rating (Scottish areas only), headline, wind, rain and cloud-free percentage
- **how many hills there I haven't done**, and how many nobody in the selected group has done
- the wind and cloud-free figures, with MWIS's sentences behind "More detail"

Tapping an area opens **My hills to do** (§6) filtered to that area. This answers "where should we go this weekend?"

---

## 5. Logging ascents

**Every ascent belongs to a walk, even history.** There's one way to record anything: a walk with days and hills in order, called a plan until it is completed (§3 glossary). Logging after the event just creates a walk that's already **Completed**. This keeps one model for plans, history and stats.

- **Log a hill (quick):** from a hill's page, one tap creates a completed one-day walk with that hill, dated today (changeable). Optional extras:
  - **who with:** solo, or a group and which members
  - **time taken:** start and finish times, or a duration
  - **notes**
- **Log a round:** a completed one-day walk with several hills **in order** (§8.1) and an optional **direction**.
- **Log a trip:** a completed walk over several days, each day being a round. For example, Knoydart: day 1 Ladhar Bheinn, day 2 Luinne Bheinn and Meall Buidhe.
- **With a group:** pick the group and tick who did which hills, the same grid as Closing a plan (§8).
- **Solo:** the walk has no group.
- Repeat ascents are allowed. Baggr counts **first ascent** towards the total and keeps every ascent in the history.
- **Seeing your ascents.** A hill's page lists each of your ascents, oldest first, with its date and the walk's title, which links to the walk. A walk with no title shows its first two hill names, as the walks list does.
- **Editing a logged walk (Must, step 3c).** A completed walk can be corrected without deleting it.
  - **Which walks.** Every completed walk, however it began. Closing a plan (§8) changes the same walk to Completed and makes no second record, so there is no plan behind it to edit and no copy of what was planned is kept.
  - **What.** The title, notes, each day's date, hills and their order, direction and time taken, who came (members and guests) and who did which hill. Days can be added and removed. The group, or that it was solo, can't be changed: delete the walk and log it again.
  - **Who.** Anyone who was on the walk, whoever logged it, and the admins of its group. On a group walk, someone who is now a Follower, or has left the group, can't (§2). Deleting stays with whoever logged it and the group's admins.
  - **How.** The "Log a walk" form, filled in with the walk's details, with the same rules (nothing in the future, at least one hill a day). Hills are reordered by dragging, or with the up and down buttons (§8.1).
  - **Kept.** An ascent's note stays for every ascent that survives the edit. Taking a hill or a person off the walk removes those ascents.
- **Importing past ascents (Must, one-off).** Existing history, back to 1987, lives in an Excel workbook (`munrotab_v7.1.xlsx`, sheets `Climbed`, `Climber` and `Munros`). It is loaded once by `php artisan ascents:import`, which creates the walks through the same code as logging, so they are ordinary completed walks. Its `RunningNo` column matches the running numbers in the Munros sheet, whose DoBIH number gives the hill.
  - **Walks.** One completed walk per group and date. The group codes are `JSD` (Stewart Duthie, solo), `CAM` (Craig McKay, solo) and `MGB` (Mountain Goats Bourich). Solo rows become solo walks owned by that person. MGB rows become group walks with Craig and Stewart as attendees and each hill ticked only for the people who did it; Craig, the group's admin, is the organiser. Consecutive dates in one group become one multi-day walk (one case: Stewart, 26 and 27 Sep 2020).
  - **Matching.** People and groups are matched by name, given on the command line rather than written into the code. The command stops if a name matches no account, or more than one.
  - **Details.** The hills in a day are ordered by running number, because the workbook has no order; direction and duration are left blank. Repeat ascents are kept (Geal-charn: Stewart solo in 2009 and with MGB in 2026; the first counts). A hill marked "climbed 5 times" with one date (Mount Keen, Craig, 8 Aug 2020) is one ascent with the note "Climbed 5 times; only this date recorded". One extra is added by hand through the command: Ethan joins the MGB walk of 26 Jun 2025 (Braeriach, Sgor an Lochain Uaine, Cairn Toul). Other MGB members aren't in the workbook and get nothing.
  - **Safety.** Every imported walk's notes say "Imported from munrotab v7.1", so a re-run skips what is already there and `--rollback` removes exactly those walks. `--dry-run` shows the walks and ascents each person would get and changes nothing. On the live site it runs through a one-off cron after a database export, with the workbook uploaded to `storage/app/private/import/` and deleted afterwards. The import was run on the live site on 25 Nov 2026 and is finished: don't run it or roll it back once walks have been edited, because a rollback would remove the edits.

---

## 6. Home and My hills

### 6.0 Home page
The Laravel welcome page at `/` goes.

- **Signed out: a plain landing page.** It shows no hills, weather or data. On a phone it fits on one screen:
  - the **hill illustration**, a wider, more detailed version of the hill mark spanning the top of the page, like a skyline at dusk
  - the **baggr** wordmark
  - the brand tagline, **"Get yourselves together."**, with the supporting line **"The hills are calling."** beneath it in smaller, lighter text
  - a **Sign in** button
  - no **Register** link, because joining is by invitation (§2). Public registration is switched off in Fortify.
- **Everything else needs signing in.** A visitor who follows a link to any page is sent to sign in, then taken to that page. **For now the hill list and hill pages stay public**, without anything from MWIS (§4), and move behind sign-in later.
- **Signed in:** `/` is the home dashboard, a phone-first view of "what's next":
  - **Next plan** (or "No plans yet, start one"), with its date, hills and the weather or outlook rating.
  - **This weekend:** the five Scottish MWIS areas for the target day with their ratings, each tapping through to the hills I still need there (the area view, §4).
  - **Progress:** X / total for my default list, with the latest ascent.
  - **Open polls** waiting for my vote.
- The starter kit's separate `/dashboard` route redirects to `/`.
- **Step 4 builds This weekend and Progress** (Munros). Next plan arrives with step 5 and Open polls with step 6. Someone who hasn't verified their email goes to the hill list instead.


Three personal views follow. Each can be filtered to **all ascents**, **solo only** or **one group**.

### 6.1 My completed hills (stats)
- **X / total** for the chosen list in large type (e.g. 147 / 282 Munros), with the percentage. A small summary row shows the other lists.
- **By section:** one row per section, using the chosen grouping (MWIS area, Murray section or Murray sub-section). Each completed hill is a small square, coloured solo or by group, with the count. Sections can be expanded to list the hills.
- **Map:** every completed hill as a dot, coloured **my colour** if done solo or the **group's colour** if done with a group. Tapping a dot shows the hill name, date and who with.
- **Headline numbers:** hills this year, first and latest ascent, highest hill, most-climbed hill, number of hills done with each group.
- **Compleation tracker:** hills left and a projected finish at the current rate.

### 6.2 My hills to do
- **Map and list** of every hill I haven't done.
- Each hill shows the **target-day forecast** for its MWIS area (§4), with the day named.
- **Sort** by weather rating, section or height. **Group** by MWIS area, Murray section or Murray sub-section.
- The selected group's view adds **who in the group also needs it**, which feeds the hill suggestions when building a plan.
- A **"start a plan" / "add to a draft plan"** action on a hill or a selection of hills.

### 6.3 History
- Pick a grouping: **MWIS area / Murray section / Murray sub-section**.
- A **timeline per section**: one row per section, with time running left to right from the first ascent to today. Each ascent is a **dot** placed at its date.
- **Dot colour:** my colour if solo, otherwise the group's colour. A **legend** shows them.
- **Tap or hover a dot** to see the hill, the date, the time taken, the notes and who was there.
- Option: **first ascents only** (the default) or **all ascents**.
- **On a phone:** the timeline scrolls sideways, section names stay fixed on the left, and a tap replaces hover.

_The design references are the "Munros Climbed" and "History" dashboards in Tableau._

---

## 7. Date polls

The plan comes first and the date second. The group usually knows roughly what it wants to do (the hills, the route, perhaps the accommodation) before it knows when. So a poll is always attached to a **draft plan** (§8). Only group members can vote, not guests.

1. Someone creates a **draft plan** with its days, hills in order and any routes, but **no dates**.
2. On the draft, they tap **Poll for dates** and add the candidate options. For a multi-day trip, each option is a start date; the trip's length comes from the plan's number of days.
3. Members mark each option **Yes / Maybe / No**. Each option shows the outlook rating for its date once it's within 14 days, and the MWIS rating once it's within 3 (§4).
4. Baggr highlights each option with **2 or more Yes votes**. The threshold is configurable per group.
5. Any attendee taps **Confirm date** on the chosen option. The plan becomes **Planned** with that date, the Yes voters set to Going and the Maybes to Maybe.
6. The poll closes and the other options are kept for the record.

A plan can also skip the poll: if the date is already known, an attendee just sets it.

---

## 8. Walk plans

### What a plan holds
A plan belongs to one group and covers **one or more days**.

| Field | Notes |
| --- | --- |
| Title | e.g. "Knoydart weekend" |
| Days | Each day has a date, its hills **in order**, a direction, and its routes (see 8.1). A plan has **at least one hill**: outings with no hills are arranged outside Baggr, on WhatsApp. Any hill in the database can be added, on a list or not, such as the Fife Marilyns West and East Lomond. |
| People | Members and guests, each Going / Maybe / Not going for the whole trip, with optional per-day attendance |
| Meeting point | Name plus a map pin or grid reference, and a time |
| Accommodation | One or more stays, each with a name, link, check-in and check-out nights, and notes |
| Money link _(should)_ | One optional link for sharing costs, e.g. the group's **Splitwise** group or a **Monzo** shared tab or payment link. Shown as a button on the plan; Baggr stores the link only (§12) |
| Notes | Free text |

### 8.1 Hills in sequence
The same rules apply to a plan day and to a logged round (§5).

- A day's hills are an **ordered list**, which can be reordered by dragging (or with up and down buttons on a phone).
- A day can have a **direction**:
  - clockwise or anticlockwise, for a horseshoe or circuit
  - north → south or south → north
  - east → west or west → east
  - out and back
  - none
- **Changing the direction reorders the hills** where Baggr can work it out. North → south sorts by latitude, west → east by longitude, and switching between clockwise and anticlockwise reverses the list. The order can still be adjusted by hand afterwards.
- The plan page shows the hills numbered in order (1, 2, 3…), joined by a line on the map. If a GPX route is attached, the route line is drawn instead.
- **Add hills to a day** by picking from the list or map, or from a GPX route (§9). The picker has a **list dropdown** (the nine lists in §3) that **defaults to Munros**. Switching it lets a day mix lists, such as a Munro and a neighbouring Corbett. The same picker is used when logging a round or trip (§5).
- A multi-day trip is a list of days, each with its own ordered hills. Hills can be moved from one day to another.

### Lifecycle

```
Draft (no date) ──poll for dates──▶ Polling ──confirm date──▶ Planned ──after last day──▶ Close ──▶ Completed
      │                                                         │  ▲
      └──────────────── set date directly ───────────────────▶ │  │ new date (set, or re-poll)
                                                       postpone │  │
                                                                ▼  │
                                                            Postponed
Draft, Polling, Planned or Postponed ──cancel──▶ Cancelled
```

- **Draft:** hills, routes, accommodation ideas and notes, with no date. Any member can view it, and any attendee can edit it.
- **Postpone** keeps everything: days and their hills, people, routes and accommodation. The plan waits with no dates until it gets new ones, either set directly or through a new date poll. Postponing sends a notification.
- **Cancel** keeps the plan for the record, marked as cancelled, and sends a notification.
- **Close** turns the plan into history (see the next section).

### Closing a plan
After the last day, any attendee taps **Close plan** and gets a grid **for each day** of people against hills, with the hills in sequence. Each attendee defaults to every hill ticked, and they untick what each person didn't do. For example, Stewart did all 3 hills and Craig did 1. The order or direction can be corrected if the day went differently from the plan. Closing:
- creates group ascents linked to the walk for each ticked cell, with an optional time taken per day
- records who actually turned up, which may differ from who was "going"
- allows an optional written note on the walk

---

## 9. Routes (GPX)

- Upload one or more GPX files to a plan day, or to a hill as a "route we used".
- Each route is **drawn on a map** on the Thunderforest Outdoors base layer (§14). Every map in Baggr uses the same base layer.
- Baggr calculates distance, total ascent and an estimated time using Naismith's rule, and shows an **elevation profile**.
- **Download** the original GPX file.
- Baggr checks which Munros the route passes within roughly 100m of, and offers to add them to that day's hills.
- Files are stored privately and served through the app, not from a public folder.

---

## 10. Notifications (ntfy)

- Each group has its own **ntfy topic**. Members install the ntfy app and subscribe.
- The topic name must be unguessable (ntfy.sh topics are open to anyone who knows the name), or Baggr runs its own ntfy server with access control.
- **Plan summary message:** dates and meeting point/time, who's coming, each day's hills, the MWIS headline with wind, rain and cloud-free percentage for each covered day, and a link to the plan.
- **When to send:**
  - automatically just after the 07:00 forecast fetch, on the first morning the walk's first day is covered (2 days before), and again on the morning before
  - automatically when a date poll opens (asking people to vote), when a poll reaches its threshold, and when a date is confirmed or a plan is postponed or cancelled
  - by hand: any attendee can tap **Send update**
- Sending runs from the Laravel scheduler.
- **Joining is the exception:** an alert that a Visitor is waiting goes to the group's admins by **email**, not ntfy (§2), because it has to reach someone who may not have subscribed to the topic yet.
- **Don't forget: follower comments (§11).** Follower comments first show on the walk page only. When this step is built, a comment from a Follower should also alert the walkers on that walk through ntfy, so a "How are you getting on?" doesn't wait for someone to open the page.

---

## 11. Check-ins for the people at home

While a walk is happening, the people at home can follow it in two layers:

1. **Check-ins** are the base. They're short, timestamped messages from the walkers, each with the place they were when they sent it. A check-in says where we were, when, and that we were fine, and that stays true however patchy the signal is. Every walker can send them from the walk page, with nothing to install.
2. **A moving dot and trail** are added for walkers who install the **OwnTracks** app. It reports positions from the background. A web page can't do that, because phones stop sharing a page's location once the screen locks.

Check-ins work without OwnTracks, and OwnTracks adds to check-ins rather than replacing them.

### What followers see
- A **share link** for each walk day. It's unguessable and **anyone who has it can open it**. It needs no account, and a partner, parent or mountain rescue team can use it. **Through the link a person can only read**, and can't post anything.
- **Followers with an account can comment.** A spouse or loved one can instead be a **Follower** in the group (§2). They sign in and see the group's walks, including the check-in page, with a box to write a comment such as "How are you getting on?". The comment carries their name and appears in the feed among the check-ins, marked as a comment. A Follower can change nothing else.
- **It never expires.** The page stays as a record of the day. Any walker on that day can **turn the link off**, and **make a new one**, from the walk page.
- **"Last check-in 12 minutes ago"** shown prominently, with the place and the time of day. Mobile signal in the hills is patchy, so a gap is normal and doesn't mean anything is wrong.
- **A feed of check-ins**, newest first, such as "11:42 · On summit of Ben Cleuch · NN902006".
- **A map** with a pin for each check-in that has a position, over the planned route and numbered hills. The latest pin is highlighted.
- **A moving dot and trail**, when a walker is sharing through OwnTracks: the group's latest position (the most recent fix from any sharing walker), with the trail behind it, on the same map. Beside it is **"Last position 4 minutes ago"**, separate from the last check-in. With no OwnTracks walker, there is no dot, and the page shows check-ins only.
- **Where they are:** the **OS grid reference** and the latitude and longitude of the latest check-in or position, whichever is newer, with its accuracy and the time it was sent. That's what Police and Mountain Rescue ask for. Followers can copy it.
- **The plan:** the hills for the day in order, the **expected back time**, and who is on the walk (the safety card in §12).
- **Progress:** which hills have been checked in at, and what's next.
- **ntfy:** followers can subscribe to the walk's **followers topic**, which is separate from the group's own topic, and get each check-in as a notification.
- **A plain note on the page:** this is a convenience, not a safety system. Check-ins may be late or missing without anything being wrong. In an emergency, call 999 and ask for Police, then Mountain Rescue.

### Check-ins
- **Quick buttons** for walkers on the walk page, one tap each:
  - "On summit of …"
  - "Heading down"
  - "Running late, back about …"
  - "Off the hill, all safe"
- **Free-text check-ins** can be sent too.
- **Position:** each check-in asks the phone for its location and attaches it, with its accuracy. If the phone won't give one, the check-in is still sent, with no pin.
- **No signal:** the page keeps the check-in on the phone, with the time it was made, and sends it when signal returns. The page has to stay open for that to work.
- **Follower comments** show on the walk page for the walkers, newest first with the check-ins. At first they raise no alert; an ntfy alert to the walkers is noted against §10.
- **Automatic entries:** "Walk started" and "Walk finished". When OwnTracks is sharing, "Reached [hill]" is added when a position comes within about 100m of a planned summit.
- **Expected back time (optional):** if the walk isn't finished an hour after this time, followers get a "no finish yet" notice. It's information, not an alarm.

### Starting and stopping
- Any attendee taps **Start walk** on the plan day. Anyone on the walk can send check-ins while it is running. Baggr only records OwnTracks positions from attendees who have chosen to share for that walk.
- **Finish walk** ends the walk and stops OwnTracks positions being recorded. The share link and its check-ins **stay**, and nothing is deleted automatically. Midnight doesn't stop the walk either, so multi-day walks work.

### Where OwnTracks positions come from
- **The OwnTracks app** is free and runs on iPhone and Android. In HTTP mode it posts each position to a URL. Baggr gives each walker a personal tracking URL, and the app sends positions in the background. If there's no signal, OwnTracks queues them and posts them later, so a gap in signal fills in afterwards.
- **Raw positions are deleted after 30 days.** Check-ins are kept for good. A trail saved to the day as a GPX route (below) is kept too.
- After the walk, the trail can be **saved to the day as a GPX route** (§9), so "the route we actually did" is kept. A saved route is drawn on the share page in place of the live trail.
- **Not in version 1:** satellite messengers such as a Garmin inReach. A walker with one can paste its share page into a check-in message.

---

## 12. Additional features

Smaller features, with priorities from the table at the top.

| Feature | Priority | Why |
| --- | --- | --- |
| **Money link on a plan**: paste a Splitwise group link, or a Monzo shared tab or payment link, and it appears as a button on the plan (and in the plan's ntfy summary) | Should | Shares costs without Baggr tracking money itself. Baggr stores the URL only and doesn't connect to either service. |
| **"Needed by the most people" hill suggestions** | Should | When building a plan, rank hills in an area by how many of those attending still need them. This sorts out the "some of us have done it" problem up front. |
| **Daylight hours** for each plan day (sunrise, sunset, hours of light) | Should | Critical for winter Munros. Calculated locally from lat/long and date, with no API needed. |
| **Add to home screen (PWA)** | Should | Baggr opens like an app on a phone. |
| **Lift sharing**: who's driving, free seats, pick-up points | Could | Most Munro days start with "who's driving?" |
| **Calendar feed (.ics)** of planned walks | Could | Plans show up in everyone's phone calendar and update automatically. |
| **Safety/route card**: plan, route, expected return time and party list, shareable by link with a non-walking contact | Could | Standard mountain safety practice. The check-in page (§11) already shows the plan, expected back time and who is on the walk; this Could is a standalone card to share before the walk. |
| **Seasonal notices** on the plan page: deer-stalking season (Aug to Oct) with a link to Heading for the Scottish Hills, and a SAIS avalanche forecast link in winter | Could | Avoids an unwelcome surprise on the day. |

---

## 13. Won't (this time) and Never

### Won't (this time)
Left out of version 1, but could come back later.

- Group photos and photo uploads (too intensive). Hill photos from Geograph are a separate Could.
- Weather sources other than MWIS and Open-Meteo.
- Tracking costs or splitting bills inside Baggr. A link out to Splitwise or Monzo is a Should (§12).
- Satellite trackers (Garmin inReach). A walker with one can paste its share page into a check-in message.

### Never
Ruled out permanently.

- **Copying or storing Walkhighlands content**: no route descriptions, text, photos or maps, and no scraping their pages for content. Baggr only stores the link to each hill's page.
- **Public profiles, following, likes, or anything social open to people outside a group.** Baggr stays small and private.

---

## 14. Decisions

| Question | Decision |
| --- | --- |
| How hills are put into sections | MWIS area, Murray section and Murray sub-section, for every hill (v0.16: replaces the names SMC and RHB; DoBIH's own RHB sections are an area category) |
| Single-day or multi-day plans | Plans can be multi-day |
| Can guests vote in polls | No. Members only; guests are added to plans by members |
| Target day early in the week | The furthest-out forecast day available |
| More than one group per person | Yes |
| Group photos | Won't (this time) |
| Weather rating | Poor if cloud-free under 40% or wind over 40mph; Marginal if wind 30–40mph; rain not rated |
| Which wind figure | Top of the main range; gusts shown, not rated |
| Which cloud-free figure | Highest in the area for the rating; full range shown |
| Plan or date first | Plan first: a draft plan with hills or route, then a date poll on it |
| How history is logged | Always as a walk (plan), created as Completed; a quick one-hill log is a one-day walk |
| Importing past ascents | Must, as a one-off command (step 3b, v0.18): the workbook's 264 ascents become about 105 completed walks for Stewart, Craig and MGB, rehearsed on a copy of the live database first. Ethan is added to the MGB Braeriach walk of 26 Jun 2025. |
| Editing logged walks | Added as step 3c (v0.20), before plans: anyone who was on the walk, whoever logged it and the group's admins can edit it, on the "Log a walk" form. The imported walks need it: they have no direction or time, and their hills are in workbook order. |
| Signed-out home page | Plain landing page: hill illustration, baggr wordmark, tagline, Sign in. No data shown. |
| Hill mark | Direction A, "Peak", with nothing on the summit; its foot sits on the wordmark's baseline |
| Tagline | Brand tagline "Get yourselves together.", supporting line "The hills are calling." |
| Who can see the app | Signed-in members only; the hill pages go behind sign-in and public registration is off |
| Hill picker default | The list dropdown defaults to Munros when planning or logging |
| Other hill lists | Nine lists: Munros, Munro Tops, Corbetts, Corbett Tops, Grahams, Graham Tops, Donalds, Donald Tops, Marilyns. A "Tops" list holds only the tops (Munro Tops is 226 hills, not 508). Marilyns are in (decided after v0.8); no Furths. |
| Who can create a group | Any signed-in member, who becomes its admin |
| Invitation links | Two ways into a group (§2): an admin **invites by email** (single-use link for that address, 7 days, role chosen by the admin: Admin, Member or Follower), or someone uses the group's **reusable link** (7 days, revocable), shared on Signal or WhatsApp, which makes them a **Visitor** who sees nothing until an admin promotes them. The link is rate-limited and a group holds at most 30 waiting Visitors. Opening a link only shows a Join button. A person with no account creates one from either link, which is the only way to register. |
| Roles | **Admin, Member, Follower, Visitor.** Visitor can't be chosen from the front end. A Follower can change nothing, sees all of the group's walks and can comment on a walk's check-in page; the open share link stays read-only for everyone else. Follower comments show on the walk page first, with an ntfy alert added in step 7. |
| Email verification | Required, except for an account made through an emailed invitation (the link proves the address) and accounts made with `users:create`, which are already verified. Verification emails go through Resend. |
| Admins removing admins | Any admin can change another admin's role or remove them, but a group always keeps at least one admin |
| Walks without hills | Not supported. Every plan and every logged walk has at least one hill; outings with none are arranged on WhatsApp. Any hill in the database can be picked, including the Fife Marilyns (West and East Lomond), which are also on the Marilyns list. |
| Map tiles | **Thunderforest Outdoors** (OpenStreetMap data, with contours and hillshading), on its free Hobby Project plan: 150,000 tile requests a month, with an API key in the page. **Preferred until we get a definitive answer from Ordnance Survey** (§15). One base layer for every map in Baggr, set in one place so it can be swapped. Rules from Thunderforest's terms: attribution to Thunderforest and OpenStreetMap contributors with working links ("Maps © Thunderforest, Data © OpenStreetMap contributors") on every map, even though Baggr is private; tiles may be cached in the browser or on the device, but **Baggr never caches or proxies tiles on its own server** and never bulk-downloads or pre-caches areas; the free plan is at Thunderforest's discretion and can be withdrawn. **Fallback:** the free OS OpenData styles through the OS Maps API (attribution "Contains OS data © Crown copyright and database right [year]"). The 1:25,000 and 1:50,000 Leisure layers are **not** planned. OS's chat assistant (22 Nov 2026, AI-generated) said Premium isn't for small projects; a human OS adviser then replied (24 Nov 2026) that the Premium layers are free up to a £1,000 monthly allowance (about 1.9 million views at 15 tiles a view) but need a payment card on file, that a key can be restricted to a domain such as baggr.co.uk, and that the API terms bar letting end users download, store or extract Premium data, which GPX download of routes drawn on a Premium map sits close to. Attribution wasn't answered; the terms apply. |
| Admin alert for waiting Visitors | An email to every Admin of the group when a Visitor arrives through the reusable link, with a link to the group page; one per arrival; approves nothing. Plus an "N waiting" badge on the Groups list (v0.17) |
| Email | **Resend** (EU region), for password resets and any later email, sent as `noreply@baggr.co.uk`. Set up and tested on 21 Nov 2026: SPF, DKIM and DMARC all pass for baggr.co.uk, with DMARC at `p=none` for now (tighten to `quarantine` once mail has flowed for a couple of weeks). Mailgun was dropped as overkill for this volume; the cPanel mailbox was not used because it shares an IP address with other customers. Click and open tracking are off. The API key lives only in the server's `.env`. |
| Weather for hills MWIS doesn't claim | Show the mapped (nearest) MWIS area's forecast, labelled "nearest area" (e.g. the Lomonds use Southeastern Highlands) |
| Which hills get photos | Every hill except those only on one of the four Tops lists, fetched locally in batches and shipped in the database dump |
| Leaving a group or deleting an account | Ascents belong to the person; leaving a group changes nothing; deleting an account removes their ascents and the group's walks show a "former member" |
| Brand colours | Hill green `#1F4D3A` and heather `#C9B5DD`, kept as design tokens |
| ntfy topics | One topic per group; no per-person topics |
| Where positions come from | Two sources (§11): a check-in carries the phone's position at the moment it is sent, and the OwnTracks app reports positions from the background for walkers who install it. Both are in version 1. |
| Geograph images | Hotlinked from Geograph's servers, with the CC BY-SA credit and a link to each photo's Geograph page. Geograph's developer confirmed by email (16 Nov 2026) that this is fine for modest, non-commercial traffic using thumbnails or the 640px size, and asked that we download and serve our own copies if traffic grows large. |
| Rating coverage | Go / Marginal / Poor is derived for the five Scottish MWIS areas only; the five English and Welsh areas show their forecast values with no rating |
| DoBIH licence | CC BY 4.0: free to import and reuse, with the credit in §3 on every page that shows hill data |
| How much of DoBIH | All of it (all countries, names, classifications, areas, maps); Scotland is the default view |
| MWIS area for new hills | A mapping from DoBIH named areas and Murray sections to MWIS areas, built in step 3; hills inherit it. The 282 Munros keep their curated MWIS area. Six straddling sections use the nearest Munro. Hills outside the ten MWIS areas show "no MWIS area" (v0.15: the Cheviots are inside, in Southern Uplands). |
| Area filter | Any DoBIH area can be picked on the hill list, My hills and the to-do list, alone or with a list (v0.14), by area category and then area (v0.16) |
| One name, one thing | A principle (§1) with a glossary (§3). "Region" is retired: DoBIH's "regions" are **hill ranges**, MWIS's are **MWIS areas**, groupings produce **sections**, and country is **country** (v0.16) |
| Hill list columns | Name, height, MWIS area, list. The list column is the hill's most significant list, shown in full or as a short code on a phone, and sortable (v0.16) |
| Search | Word-start matching on names; area names (DoBIH and MWIS) from four letters (v0.16) |
| Getting DoBIH onto the live site | Import locally, then send a database dump; the Access file never goes to the host |
| Hill pages and sign-in | The hill list and hill pages stay public for now; the rest of the app goes behind sign-in |
| MWIS before permission | Build step 4, switched off by default; local testing only until MWIS agrees (v0.22) |
| Forecast fetch times | Twice a day: 07:00, and 18:00 after the main afternoon issue (v0.22) |
| Past forecasts | Kept: the last version fetched for each area and date; earlier versions aren't kept (v0.22) |
| MWIS and visitors | The MWIS logo, links, areas and (later) weather show to signed-in people only (v0.21) |
| Prioritisation | MoSCoW, scoped to version 1, plus a **Never** tier for permanent exclusions (see Priorities at the top) |
| Check-ins for followers | In scope (Should): check-ins are the base and work for everyone; OwnTracks adds a moving dot and trail for walkers who install it. The share link is open to anyone who has it, needs no login, and **never expires**; any walker can turn it off and make a new one. Check-ins are kept for good; raw OwnTracks positions are deleted after 30 days. ntfy for followers. |
| Weather beyond MWIS's 3 days | Open-Meteo outlook per hill, up to 14 days, clearly labelled |
| Who can edit, confirm or close a plan | Any attendee |
| Costs | Not tracked in Baggr; plans *should* carry a link to Splitwise or Monzo |
| Several hills in one walk | Each day's hills are an ordered list with an optional direction; rounds and multi-day trips can be logged as well as planned |

## 15. Open questions

1. **MWIS permission:** Craig sent MWIS a request through their contact form on 22 Nov 2026, covering the logo and linking, and a daily 07:00 read of the ten areas showing a few short extracts (headline, wind, how wet, cloud-free chance, freezing level), credited and linked. Waiting for a reply. Since then it has become twice a day, at 07:00 and 18:00, and past forecasts are kept (v0.22): tell MWIS both when following up. If they say no, Baggr shows the area and a link only (§4). Step 4 was built ahead of the reply, with forecasts switched off (§4); permission is needed before they are switched on live.
2. **Map provider:** Thunderforest Outdoors is preferred (§14) until we get a definitive reply. To do: Craig signs up for the free Thunderforest plan (checking that it asks for no payment card and telling Claude before accepting any terms that mention charges), OS support answered on 24 Nov 2026 (see §14: Premium needs a card, a key can be restricted to a domain, and Premium data can't be downloaded or extracted by end users), so nothing more is needed from OS unless the plan changes. Not needed before step 8 (GPX routes), the first map.
_Resolved and moved to §14: Geograph hotlinking, ntfy topics, hill lists, live tracking source, brand colours (16 Nov 2026); DoBIH licence (17 Nov 2026); email service, now Resend and tested (21 Nov 2026)._

---

## 16. Suggested build order

The Musts come first, then the Shoulds, then the Coulds.

**Musts**

0. **Done (release 3).** **Look and feel and the landing page** (§1, §6.0): the hill mark, wordmark and app icons; a plain landing page instead of the Laravel welcome page; registration off; every page behind sign-in. Until the dashboard exists, `/` sends signed-in users to the hill list.
1. **Groups, walks and ascents** (§2, §5, §8.1): groups with the four roles, invitations by email and the reusable link (Visitors and approval), walks with days and ordered hills, and logging completed walks (quick, round, trip).
2. **My hills, core** (§6.1, §6.2): X / total, the by-section breakdown, and the to-do list without weather yet.
3. **Hill list improvements** (§3): grouping, the MWIS badge, filters, the area filter, and the DoBIH area to MWIS area mapping for every hill.
3a. **Admin alert for waiting Visitors** (§2): an email to the group's admins when someone arrives through the reusable link, and an "N waiting" badge on the Groups list. Small, and done before step 4 so it is in place as more people are brought in.
3b. **Load the past ascents** (§5): the one-off `ascents:import` command, rehearsed on a copy of the live database, then run on the live site. Done before step 4 so the to-do list and the group's "who still needs it" are real.
3c. **Edit a logged walk** (§5): the "Log a walk" form for an existing walk, and dragging to reorder hills (§8.1, asked for in step 1 but only the up and down buttons were built). Done before step 5, which reuses the form.
4. **MWIS data** (§4): the 07:00 and 18:00 fetches, the target day, the rating, weather on hills and on the to-do list, the area view, the home dashboard.
5. **Plans** (§8): draft plans, multi-day, attending, postpone, cancel, close into ascents.
6. **Date polls** (§7): poll on a draft plan, vote, confirm the date.
7. **Notifications** (§10). Includes the ntfy alert for Follower comments (§11).
8. **GPX routes** (§9): upload, draw, download.
9. **Other hill lists** (§3): import, list selector, MWIS mapping for the new hills. **Mostly done:** the whole-DoBIH import and the list selector, including Marilyns, are live (releases 4 and 5). The MWIS mapping moved into step 3 (v0.14). Still to do: running the Geograph photo fetch for the new hills.

**Shoulds**

10. **Maps and the history timeline** (§6).
11. **Open-Meteo outlook** (§4).
12. **Check-ins for the people at home** (§11), including Follower comments on the walk page.
13. **Should features from §12**: money link, hill suggestions, daylight, PWA.

**Coulds**

14. The Could items, as time allows.

---

## 17. Data model sketch

_For Code to check against the database and refine._

| Table | Key columns |
| --- | --- |
| `hills` | (exists) + Geograph photo fields; `dobih_number` (exists) is the key. `region`, `smc_section` and `rhb_section` (and their descriptions) are dropped: a hill's Murray section and sub-section come from `dobih_section`. To add from DoBIH: country, feet, drop, col grid reference and height, 10-figure grid reference, feature, parent Marilyn/SMC hill, survey and revision notes, OS map sheets. `name` stops being unique, and `slug` gets a suffix where names clash. |
| `hill_names` | `hill_id`, `name`, `sort` (alternate names, for search) |
| `areas` / `area_hill` | `code`, `name`, `type` (hill_range / murray_section / rhb_section / island / county / historic_county / authority / london_borough / named_area / other), `country`; `area_id`, `hill_id`, `is_alt`, `is_county_top`. `areas.mwis_area` (nullable) is the weather mapping. |
| `hill_classes` / `class_hill` | `code`, `name`, `show_in_list`; `hill_id`, `hill_class_id`. The nine lists (§3) are the classes Munro, Munro Top, Corbett, Corbett Top, Graham, Graham Top, Donald, Donald Top and Marilyn. |
| `os_maps` / `hill_map` | `type` (e.g. OS25), `ref` (e.g. 047W), `name`; `hill_id`, `os_map_id` |
| `hill_lists` / `hill_list_hill` | `name`, `slug`; `hill_list_id`, `hill_id` |
| `users` | (exists) + `colour`, `tracker_token` (personal OwnTracks URL). |
| `groups` | `name`, `colour`, `ntfy_topic`, `poll_threshold`, `join_token` and `join_token_expires_at` (the reusable link; null when revoked) |
| `group_user` | `group_id`, `user_id`, `role` (admin/member/follower/visitor) |
| `group_invites` | `group_id`, `email`, `role` (admin/member/follower), `token`, `invited_by`, `expires_at`, `accepted_at` (single use; cancelled by deleting) |
| `guests` | `group_id`, `name` |
| `ascents` | `user_id`, `hill_id`, `walk_day_id` (required: every ascent belongs to a walk day), `notes`. Date and group come from the walk. |
| `walks` | `group_id` (null = solo), `title`, `status` (draft/polling/planned/postponed/cancelled/completed), `organiser_id`, meeting point fields, `money_url` (nullable), `notes`. Logged history (quick hill, round, trip or import) is a walk created as `completed`. |
| `walk_days` | `walk_id`, `date` (nullable when postponed), `day_number`, `direction` (nullable enum), `started_at`, `finished_at` or `duration_minutes` |
| `walk_day_hill` | `walk_day_id`, `hill_id`, `position` (order in the day) |
| `walk_attendees` | `walk_id`, `user_id` or `guest_id`, `rsvp` (going/maybe/no), `attended` |
| `walk_stays` | `walk_id`, `name`, `url`, `check_in`, `check_out`, `notes` |
| `poll_options` / `poll_votes` | `walk_id`, `start_date`, `chosen` (bool); `poll_option_id`, `user_id`, `vote` |
| `routes` | `walk_day_id` or `hill_id`, `file_path`, `distance_m`, `ascent_m` |
| `forecasts` | `source` (mwis/open-meteo), `mwis_area` or `hill_id`, `forecast_date`, `issued_at`, `headline`, `wind_text`, `wind_max_mph`, `rain_text`, `cloud_free_text`, `cloud_free_max_pct`, `freezing_level_text`, `rating` (nullable), `raw` |
| `tracking_sessions` | `walk_day_id`, `share_token` (null once turned off), `followers_ntfy_topic`, `started_at`, `finished_at`, `expected_back_at` |
| `check_ins` | `tracking_session_id`, `user_id` (null for automatic), `kind` (manual/auto/comment; a comment is from a Follower and has no position), `body`, `lat`, `lon`, `accuracy`, `made_at` (when the walker sent it), `created_at` (when it arrived). Kept for good. |
| `positions` | `tracking_session_id`, `user_id`, `lat`, `lon`, `alt`, `accuracy`, `recorded_at`. OwnTracks fixes only; deleted after 30 days. |
| `notifications_log` | `walk_id`, `type`, `sent_at`, `body` |
