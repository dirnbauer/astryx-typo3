# Portrait map for the element demo content

The element demo content (`ContentBlocks/ContentElements/*/library.json`,
`library.de.json` and `fixture.json`) draws on one small image pool in
`Resources/Public/Images/`. This file records what each portrait shows and
which fictional person it belongs to, so that new or rewritten demo content
keeps every face with the same name.

Fixtures use placeholder people ("The first card", "Name of the speaker"), so
the name map below applies to `library.json` and `library.de.json` only.

## Rules

1. A named person has exactly one portrait, in every element and in both
   languages. Look the name up here before you add or change a portrait.
2. The portrait fits the name's usual gender and the stated role or age. If no
   free portrait fits, change the person's first name (never the surname) in
   every element and both languages, and update this file.
3. One element never shows the same portrait for two different people. The
   only exceptions are listed under "Known repeats".
4. `library.de.json` uses the same image paths as `library.json`.
5. Alt text names the person and describes the photo, in one sentence:
   - English: `Portrait of {name}, {English description}.`
   - German: `Porträt von {name}, {German description, dative}.`
   - Social handles: `Profile photo of @handle, …` / `Profilbild von @handle, …`
6. A new person goes on a portrait that is not yet used in the same fictional
   business if possible. Reusing a portrait in a different business is fine.

## Portrait sheet

| File | Gender | Apparent age | What the photo shows |
|---|---|---|---|
| p01 | woman | 50–60 | Short grey pixie cut, navy blazer over a white top. Executive look. |
| p02 | man | 30–38 | Dark wavy hair, full dark beard, light blue open-collar shirt. |
| p03 | woman | 28–35 | Dark hair in a ponytail, navy polo shirt (workwear). |
| p04 | man | 60–70 | Grey hair, round tortoiseshell glasses, navy cardigan over a blue shirt. |
| p05 | woman | 45–52 | Shoulder-length brown hair, navy knit jumper. |
| p06 | man | 40–50 | Shaved head, stubble, yellow high-visibility jacket. |
| p07 | woman | 35–45 | Taupe headscarf, black top. |
| p08 | man | 18–24 | Curly brown hair, navy apprentice work shirt. |
| p09 | woman | 55–65 | Long grey hair, round wire glasses, blue patterned blouse. |
| p10 | man | 55–62 | Grey hair, moustache, checked shirt with a pen in the pocket. |
| p11 | woman | 30–38 | Blonde bob, white shirt. |
| p12 | man | 45–52 | Dark hair greying at the temples, navy fleece over a blue shirt. |
| p13 | woman | 60–70 | Grey hair pinned up, paisley scarf, pearl earrings. |
| p14 | man | 28–35 | Brown hair, beard, round glasses, green sweatshirt. |
| p15 | woman | 22–30 | Long dark hair, white lab coat. |
| p16 | man | 50–58 | Tousled grey-brown hair, grey stubble, dark quilted gilet. |

Alt-text descriptions used in the demo content:

| File | English | German (dative) |
|---|---|---|
| p01 | a woman in her fifties with short grey hair and a navy blazer | einer Frau in den Fünfzigern mit kurzem grauem Haar und dunkelblauem Blazer |
| p02 | a man in his thirties with a dark beard and a light blue shirt | einem Mann in den Dreißigern mit dunklem Bart und hellblauem Hemd |
| p03 | a woman in her thirties with dark hair tied back and a navy polo shirt | einer Frau in den Dreißigern mit zurückgebundenem dunklem Haar und dunkelblauem Poloshirt |
| p04 | a man in his sixties with grey hair, round glasses and a navy cardigan | einem Mann in den Sechzigern mit grauem Haar, runder Brille und dunkelblauer Strickjacke |
| p05 | a woman in her late forties with shoulder-length brown hair and a navy jumper | einer Frau Ende vierzig mit schulterlangem braunem Haar und dunkelblauem Pullover |
| p06 | a bald man in his forties wearing a high-visibility jacket | einem Mann in den Vierzigern mit Glatze und Warnjacke |
| p07 | a woman in her late thirties wearing a taupe headscarf and a black top | einer Frau Ende dreißig mit taupefarbenem Kopftuch und schwarzem Oberteil |
| p08 | a young man with curly brown hair and a navy work shirt | einem jungen Mann mit braunen Locken und dunkelblauem Arbeitshemd |
| p09 | a woman in her late fifties with long grey hair, round glasses and a patterned blouse | einer Frau Ende fünfzig mit langem grauem Haar, runder Brille und gemusterter Bluse |
| p10 | a man in his late fifties with grey hair, a moustache and a checked shirt | einem Mann Ende fünfzig mit grauem Haar, Schnurrbart und kariertem Hemd |
| p11 | a woman in her thirties with a blonde bob and a white shirt | einer Frau in den Dreißigern mit blondem Bob und weißem Hemd |
| p12 | a man in his late forties with greying hair and a navy fleece | einem Mann Ende vierzig mit grau meliertem Haar und dunkelblauer Fleecejacke |
| p13 | a woman in her sixties with grey hair pinned up and a patterned scarf | einer Frau in den Sechzigern mit hochgestecktem grauem Haar und gemustertem Tuch |
| p14 | a man in his early thirties with a beard, round glasses and a green sweatshirt | einem Mann Anfang dreißig mit Bart, runder Brille und grünem Sweatshirt |
| p15 | a young woman with long dark hair and a white coat | einer jungen Frau mit langem dunklem Haar und weißem Kittel |
| p16 | a man in his fifties with grey stubble and a dark quilted gilet | einem Mann in den Fünfzigern mit grauem Bartschatten und dunkler Steppweste |

## Name → portrait

### Aubruck surveying office (Hollerau, Nauwang, Kettbach)

Staff:

| Name | Portrait | Role | Elements |
|---|---|---|---|
| Helene Marchetti | p01 | Managing partner (signs the leader letter) | leadership-row, team-grid, team-list, leader-letter, founder-story (text), team-group-photo (text) |
| Andrea Brandstätter | p05 | Partner, surveying | leadership-row, mentor-list, person-profile, team-departments, team-facepile, team-function-columns, team-grid, team-list |
| Katja Sunder | p11 | Partner, administration | contact-person, leadership-row, office-people, team-function-columns, team-grid, team-list |
| Tobias Reindl | p12 | Partner, road and drainage planning | leadership-row, team-function-columns, team-grid, team-list |
| Milan Hrdlicka | p16 | Senior surveyor | mentor-list, person-quote, speaker-lineup, team-departments, team-facepile, team-function-columns, team-grid, team-list |
| Clara Hofstätter | p03 | Drone surveyor | mentor-list, speaker-lineup, team-departments, team-facepile, team-function-columns, team-grid, team-list |
| Anja Feuerstein | p07 | Land surveyor, Kettbach | office-people, team-departments, team-facepile, team-function-columns, team-list |
| Lea Wimmer | p15 | Apprentice, third year | person-interview, team-departments, team-facepile, team-function-columns, team-list |
| Hanno Grasser | p06 | Site supervisor | team-departments, team-facepile, team-function-columns, team-list |
| Roland Lachner | p10 | Road planner, Nauwang | office-people, team-facepile, team-function-columns, team-list |
| Firat Yildiz | p02 | Project engineer, drainage | mentor-list, team-facepile, team-function-columns, team-grid, team-list |
| Emre Aydin | p14 | GIS and data specialist | mentor-list, team-function-columns, team-list |
| Paul Ebner | p08 | Survey software and IT | team-function-columns, team-list |
| Norbert Pichlmayr | p04 | Instruments and vehicles | team-function-columns, team-list |
| Renate Söllner | p13 | Quality manager | team-function-columns, team-list |
| Doris Achleitner | p09 | Accounts and payroll | team-function-columns, team-list |
| Sabine Wolkersdorfer | p09 | Head of drawing office (shares p09, see below) | team-function-columns, team-grid, team-list |
| Nadja Reithofer | p01 | Communications (shares p01, see below) | author-box, team-function-columns, team-list |
| Christoph Waldner | p12 | Recruitment and apprenticeships (shares p12, see below) | team-function-columns, team-list |

Founder, advisers, speakers, mentors and alumni:

| Name | Portrait | Role | Elements |
|---|---|---|---|
| Gerhard Tulln | p04 | Founder, 1994 to 2016 | founder-story |
| Ulrike Traxler | p13 | Professor of surveying, Technische Hochschule Sandau | advisory-board, speaker-featured, speaker-lineup |
| Bernd Kofler | p10 | Formerly at the Sandau cadastral office | advisory-board, speaker-lineup |
| Ayse Demir | p07 | Sandau Tiefbau | advisory-board |
| Georg Stauber | p12 | Hollerau building department | advisory-board |
| Martin Obermayr | p06 | Sandau Tiefbau | speaker-lineup |
| Gudrun Haslinger | p09 | Nauwang municipal works | speaker-lineup |
| Helene Marek | p11 | Career-change adviser, chamber of commerce | mentor-list |
| Verena Kolb | p11 | Alumna, 2011–2019 | alumni-list |
| Miriam Deutsch | p03 | Alumna, 2016–2023 | alumni-list |
| Tanja Ebersberg | p15 | Alumna, 2018–2024 | alumni-list |
| Simon Baumgartner | p02 | Alumnus, 2005–2014 | alumni-list |
| Nikolaus Ferk | p14 | Alumnus, 2013–2020 | alumni-list |
| Josef Rauner | p10 | Alumnus, 1996–2021, retired; also a footpath volunteer | alumni-list, contributor-wall |

Footpath survey volunteers (contributor-wall uses each portrait once):

| Name | Portrait | Name | Portrait |
|---|---|---|---|
| Heidi Aigner | p01 | Walter Bösch | p04 |
| Ingrid Pfeil | p09 | Markus Zehetner | p12 |
| Elfriede Rauch | p13 | Dominik Sailer | p08 |
| Beate Kirchner | p05 | Ferdinand Oswald | p16 |
| Silvia Neuhold | p07 | Thomas Kienast | p06 |
| Regina Mairhofer | p11 | Alexander Prem | p02 |
| Barbara Lindner | p03 | Josef Rauner | p10 |
| Karin Steinbichler | p15 | Michael Prager | p14 |

### Stock software (wholesale customers)

| Name | Portrait | Role | Elements |
|---|---|---|---|
| Katrin Weissgruber | p01 | Managing director, Weissgruber Beschläge | review-list, testimonial-carousel, testimonial-grid |
| Ines Kofler | p05 | Head of finance, Ostheim Landtechnik | review-list, testimonial-carousel, testimonial-metric, testimonial-video (named, no portrait) |
| Doris Ebner (= @einkauf_doris) | p09 | Head of purchasing, Hollrigl Elektrogroßhandel | review-list, testimonial-carousel, testimonial-grid, social-mentions |
| Lena Brandtner | p03 | Owner, Brandtner Farben | review-list, testimonial-grid |
| Dr. Ingrid Wallnöfer | p13 | Chair of the digitalisation committee, Wholesale Association | endorsement-single |
| @lager_hanna | p07 | Forum user | social-mentions |
| @baustoff_nora | p15 | Forum user | social-mentions |
| Stefan Aigner | p16 | Works manager, Kirnbach Werkzeugbau | review-list, testimonial-carousel, testimonial-grid |
| Tobias Reisinger | p02 | Dispatch manager, Marbeck Verpackung | review-list, testimonial-grid |
| Milan Prohaska (= @prohaska_m) | p06 | Warehouse manager, Talmann Sanitärhandel | testimonial-grid, testimonial-portrait, social-mentions |
| Hannes Wurzer (= @h_wurzer) | p14 | IT lead, Presswerk Neuhaus | testimonial-carousel, social-mentions |
| Gerald Auracher | p12 | Branch manager, Auracher Gartenbedarf | testimonial-carousel, customer-story-quote |
| @werkstatt_tobi | p08 | User-group member | social-mentions |

### Marnau Institute (river data)

| Name | Portrait | Role | Elements |
|---|---|---|---|
| Hanna Vogt | p05 | Science editor | article-meta, article-header (byline, no portrait) |
| Miriam Kaltner | p01 | Biology teacher, Talhof secondary school | quote-carousel |
| Dr. Feline Oberkirch | p15 | Hydrology group, Talhof university of applied sciences | quote-carousel |
| Peter Sonnleitner | p16 | Operations manager, Reidau treatment works | quote-carousel, quote-portrait |
| Andrej Lubic | p10 | Water rights officer, district authority | quote-carousel |

### Other businesses

| Name | Portrait | Business | Role | Elements |
|---|---|---|---|---|
| Ines Kraml | p07 | Kestrel Field Ops | Support lead | hero-profile |
| Tobias Reinsch | p04 | Kestrel Field Ops customer | Managing director, Brenner Aufzugstechnik | hero-quote |
| Ingrid Halmweger | p05 | Falkensteg Gebäudetechnik | Head of service | footer-contact-person |
| Ruth Amsler | p13 | Shift planner customer | Operations manager, Fennwood Garden Centres | feature-proof |
| Ines Straubinger | p11 | Aichbrunn Kasse (bakery tills) | Accounts, Austria West | conversion-contact-person |

Named without a portrait: Marlen Osterhage (Hafner Sanitär, hero-article byline).

## Known repeats

There are more people than portraits, so portraits repeat. The rules above
keep the repeats out of sight where possible.

- **Within one element (unavoidable):** Aubruck has 19 staff (10 women,
  9 men) and the pool has 8 women and 8 men.
  - team-list (19 people) shows three portraits twice: p01 Helene Marchetti /
    Nadja Reithofer, p09 Doris Achleitner / Sabine Wolkersdorfer, p12 Tobias
    Reindl / Christoph Waldner.
  - team-function-columns (18 people, no Helene Marchetti) shows p09 and p12
    twice.
  - Three extra portraits would remove these repeats (see "Pending images").
- **Within Aubruck:** founder, advisers, speakers, mentor, alumni and
  volunteers share faces with staff. No pair ever appears in the same element.
- **Across businesses:** every portrait is used by four or five people in
  total, spread over different businesses:

| Portrait | People |
|---|---|
| p01 | Helene Marchetti, Nadja Reithofer, Heidi Aigner (Aubruck); Katrin Weissgruber (stock); Miriam Kaltner (Marnau) |
| p02 | Firat Yildiz, Simon Baumgartner, Alexander Prem (Aubruck); Tobias Reisinger (stock) |
| p03 | Clara Hofstätter, Miriam Deutsch, Barbara Lindner (Aubruck); Lena Brandtner (stock) |
| p04 | Norbert Pichlmayr, Gerhard Tulln, Walter Bösch (Aubruck); Tobias Reinsch (Kestrel) |
| p05 | Andrea Brandstätter, Beate Kirchner (Aubruck); Ines Kofler (stock); Hanna Vogt (Marnau); Ingrid Halmweger (Falkensteg) |
| p06 | Hanno Grasser, Martin Obermayr, Thomas Kienast (Aubruck); Milan Prohaska (stock) |
| p07 | Anja Feuerstein, Ayse Demir, Silvia Neuhold (Aubruck); @lager_hanna (stock); Ines Kraml (Kestrel) |
| p08 | Paul Ebner, Dominik Sailer (Aubruck); @werkstatt_tobi (stock) |
| p09 | Doris Achleitner, Sabine Wolkersdorfer, Gudrun Haslinger, Ingrid Pfeil (Aubruck); Doris Ebner (stock) |
| p10 | Roland Lachner, Bernd Kofler, Josef Rauner (Aubruck); Andrej Lubic (Marnau) |
| p11 | Katja Sunder, Helene Marek, Verena Kolb, Regina Mairhofer (Aubruck); Ines Straubinger (Aichbrunn) |
| p12 | Tobias Reindl, Christoph Waldner, Georg Stauber, Markus Zehetner (Aubruck); Gerald Auracher (stock) |
| p13 | Renate Söllner, Ulrike Traxler, Elfriede Rauch (Aubruck); Dr. Ingrid Wallnöfer (stock); Ruth Amsler (Fennwood) |
| p14 | Emre Aydin, Nikolaus Ferk, Michael Prager (Aubruck); Hannes Wurzer (stock) |
| p15 | Lea Wimmer, Tanja Ebersberg, Karin Steinbichler (Aubruck); @baustoff_nora (stock); Dr. Feline Oberkirch (Marnau) |
| p16 | Milan Hrdlicka, Ferdinand Oswald (Aubruck); Stefan Aigner (stock); Peter Sonnleitner (Marnau) |

## Renamed people

- **Marlene Ostermann → Helene Marchetti** (Aubruck managing partner), so the
  name matches `signature/signature-01.png` under the leader letter. Changed
  in every element and both languages. Note: the mentor list also has an
  outside adviser called Helene Marek.

## Other images in the pool

What the scene, gallery and pair photos show, and which demo business they
suit. Reuse is fine; a photo only has to fit the text next to it.

| File | Shows | Suits |
|---|---|---|
| scene/bakery-counter | Baker with a tray of pastries, colleague at a tablet till | Aichbrunn Kasse |
| scene/cafe-morning | Barista at an espresso machine in a quiet café | Aichbrunn Kasse |
| scene/control-desk | Grey-haired operator facing a wall of plant monitors | control rooms (not used in library content) |
| scene/counter-service | Two men with an order form at a trade counter, shelves behind | stock software, Falkensteg parts counter |
| scene/delivery-van | White van, rear doors open, crates inside, industrial street at dusk | delivery rounds, dispatch |
| scene/drawing-review | Hands on a building plan with a scale ruler and pencil | Aubruck |
| scene/field-sampling | Woman in waders taking a river water sample | Marnau Institute |
| scene/handover-keys | Keys and papers handed over at a front door | property handovers (not used in library content) |
| scene/lab-bench | Scientist pipetting sample tubes | Marnau lab (not used in library content) |
| scene/meeting-two | Man and woman at a table with a laptop | meetings, demos, procurement |
| scene/night-shift | Joiner at a bench, seen through a workshop window at night | workshops (unused) |
| scene/office-standup | Four colleagues around a monitor in a loft office | office teams, planning |
| scene/pipework | Copper pipes and brass valves on a concrete wall | plumbing, heating |
| scene/practice-reception | Physio reception: form on a clipboard, booking screen | practice software |
| scene/river-gauge | Gauge post in a river beside a solar-powered logger mast | Marnau station 14 |
| scene/roof-plant | Rooftop air-handling units and a steel walkway | building services |
| scene/server-rack | Open network cabinet with blue patch cables | IT, basements |
| scene/site-survey | Two surveyors in hi-vis with a total station on a building site | Aubruck |
| scene/substation | Fenced electrical substation in hills | utilities (not used in library content) |
| scene/technician-tablet | Technician with a rugged tablet beside a boiler | field service, Falkensteg |
| scene/training-room | Empty chairs in a circle, flip chart, brick walls | courses, meet-ups, events |
| scene/treatment-room | Physio couch and exercise bands | practice software |
| scene/warehouse-aisle | Worker picking parts in a shelved aisle, trolley | stock software |
| scene/workshop-bench | Metalworking bench, vice, drill press | workshops |
| gallery/g01–g06 | Weir, roadside logger cabinet, stream with marker post, rain gauges, flooded meadow, culvert | Marnau Institute |
| gallery/g07 | Wall of labelled parts drawers | workshops, stock |
| gallery/g08 | Pallets of boxed goods under a skylight | warehouses |
| gallery/g09 | Service-van interior with racking and tools | field service, Aubruck vans |
| gallery/g10 | Hands turning a valve with a spanner | plumbing, building services |
| gallery/g11 | Quiet street of small shopfronts | small shops |
| gallery/g12 | Printed schedules pinned to a corridor noticeboard | rotas, dispatch boards |
| pair/refit-before, refit-after | The same bakery shopfront before and after a refit | bakery |
| signature/signature-01 | Signature reading "Helene Marchetti" | leader letter |

## Pending images

These slots still hold a stand-in because the pool has nothing that fits:

| New file | What it should show | Replaces |
|---|---|---|
| logo/auracher.png | Wordmark reading "Auracher" (garden-supply dealer) | case-study-teaser (meeting-two.jpg) and customer-story-quote (portrait p12) in library content |
| logo/falkensteg.png | Wordmark reading "Falkensteg" (building services) | footer-brand-newsletter (workshop-bench.jpg) and footer-utility-columns (pipework.jpg) in library content |
| qr/falkensteg-app.png | A plain black-on-white QR code, square, no logo | footer-app-badges (technician-tablet.jpg; the text says "Scan the code") |
| scene/week-planner.jpg | A laptop in a garden-centre back office showing a weekly shift grid: seven day columns, staff rows, coloured shifts, one shift with a red edge, a budget line along the bottom, a tray of unfilled shifts, a greyed-out publish button. No legible text. | feature-callouts and feature-showcase (g12.jpg) |
| pair/backoffice-before.jpg, pair/backoffice-after.jpg | The same staff back-office wall at a garden centre, same framing. Before: a whiteboard rota, a clock-card rack, a folder of swap notes. After: the wall cleared, one wall-mounted tablet by the staff entrance. | feature-before-after library content (bakery pair) |
| scene/team-anniversary.jpg | Group photo of 20 colleagues in three rows in a yard behind an office, overcast June day. Back row 7 men; middle row 4 women and 2 men; front row 6 women (one in a headscarf, one about 19) and one man about 70. | team-group-photo library content (empty training room) |
| portrait/p17–p19.jpg (optional) | Two more women (about 55, and about 40) and one more man (about 45), same portrait style | lets Nadja Reithofer, Sabine Wolkersdorfer and Christoph Waldner have their own faces and removes the team-list repeats |
| scene/lift-shaft.jpg (optional) | A technician working in a lift machine room or shaft | hero-slideshow ("lift shafts" has no photo yet) |
