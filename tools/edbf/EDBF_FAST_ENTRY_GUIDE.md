# EDBF Crew Lists — Fast Entry Tool

A console script that fills an EDBF free-crew-list class in about **one second per crew**,
instead of clicking every seat by hand. Reverse-engineered and verified live on
`https://edbf.idbfchamps.org/CrewListsFree.php`.

Files: `edbf_fast_entry.js` (the tool), this guide.

---

## Why it works (the short version)

- The page already contains the **whole athlete roster** — id, name, gender,
  category, and paddling side — inside its pool tables. So every athlete is
  addressable by id; no searching or filtering needed.
- The boat **seat** widgets ignore scripted clicks (they need a real mouse), but
  the **pool rows** are plain HTML and respond to synthetic events. The script
  therefore drives placement from the pool side: it marks the target seat as
  "selected" (the page global `selPos`), then fires a mouse sequence on the
  athlete's pool row, which drops them into that seat.
- **Nothing is written to the server until `saveCL()` runs.** Every step before
  that is client-side only. If anything looks wrong, reload the page and it's
  gone.

This is also why the earlier pixel-clicking approach was slow and fragile:
it fought the seat widgets with real coordinates. This approach sidesteps them.

---

## Getting current crew data

Crew data comes from your **live app API**, not any saved file:

```
https://dbcrews.motion.rs/api/public/crews?competition=1&team=1
```

`edbf_crews_current.js` is a ready-made snapshot of that API, exposing
`window.EDBF_CREWS` — an object keyed by crew name (all 21 crews). Regenerate it
whenever your app changes (the API is the source of truth; any saved JSON goes
stale). Each athlete also carries an `edbfId` field: it's empty today, so the
tool matches by name, but the moment you start populating `edbfId` in your app
the tool uses it directly and name-matching stops mattering.

Because the data is always pulled fresh and the **dry run flags every mismatch
per crew**, changes in your app surface automatically at entry time — you don't
have to track them by hand.

## What you need per crew

A crew object in this shape (this is exactly what `EDBF_CREWS['<name>']` gives you):

```js
const crew = {
  name: 'SM BCP Open 2000m',
  athletes: [
    { role:'drummer', side:null,    row:null, name:'Bojana Šepa' },
    { role:'paddler', side:'left',  row:1,    name:'Tadijana Milićević' },
    { role:'paddler', side:'right', row:1,    name:'Dragana Jovanović' },
    // ... paddlers left rows 1..N, right rows 1..N ...
    { role:'helm',    side:null,    row:null, name:'Nela Cajković' },
    { role:'reserve', side:null,    row:null, name:'Ivana Stojković Memišević' },
  ]
};
```

- `role`: `drummer` | `paddler` | `helm` | `reserve`
- `side`: `left` | `right` (paddlers only; `null` otherwise)
- `row`: 1-based bench number (paddlers only). Small boats have 5 rows, standard
  boats 10. The script reads the loaded class and adapts automatically.
- `name`: any order ("First Last" or "Last First"); diacritics and the Serbian
  `đ`/`Đ` = `dj` spelling are handled.

You can export these straight from your app / API in this format.

---

## Steps

1. Log in and open **MENU → Crew list** (`CrewListsFree.php`).
2. **Select the class/race** you want to fill, so its seats render.
3. Open the browser console (F12 → Console).
4. Paste the entire contents of `edbf_fast_entry.js` and press Enter.
   You'll see: `EDBF fast-entry loaded.`
5. Paste `edbf_crews_current.js` (defines `window.EDBF_CREWS`), then pick the crew
   matching the class you selected:
   ```js
   const crew = EDBF_CREWS['SM BCP Open 2000m'];
   ```
   (Or paste a hand-written `crew` object in the same shape.)
6. **Dry run first — places nothing:**
   ```js
   edbfDryRun(crew);
   ```
   It prints a table of every athlete → resolved id + target seat, and flags any
   `NOT FOUND` / `NO SEAT`. Fix names or data until it says `✓ all resolved`.
7. **Fill without saving:**
   ```js
   edbfFill(crew);
   ```
   The boat fills on screen. Eyeball it. Nothing is saved yet.
8. **Save when happy:**
   ```js
   edbfFill(crew, { save:true });
   ```
   This re-fills and calls `saveCL()`. It **refuses to save** if any athlete is
   unresolved, so you can't half-save a crew.

To abort at any time with zero consequences: **reload the page.**

---

## Notes, limits, safety

- **Captain = Helm** is set automatically (the helm's id is written to the
  `captain` dropdown), matching your standing rule.
- **Wrong-side placement**: if you seat a left-preferring paddler on the right,
  EDBF marks the seat `wrongside` (yellow-ish) but still accepts it — same as
  manual entry. The dry run shows the seat, so you'll see it.
- **Age eligibility**: EDBF only lists athletes eligible for the class in the
  pool. If someone is age-blocked (e.g. a Senior A paddler in a Senior B class),
  their id won't resolve and the dry run flags them — place those by hand.
- **Duplicate names**: if two eligible athletes share a normalized name, the
  script warns; disambiguate by hand for that seat.
- **Native popups** ("OK") are auto-accepted during fill so they can't stall it.
- This is an unofficial helper driving the site's own functions in your logged-in
  session. It does exactly what manual entry does, just faster. It writes only on
  `saveCL()`. Use the dry run every time.

---

## Verified

Logic was checked live against an already-correct class (SM BCP Open 2000m):
all 14 athletes resolved to the right id **and** the right seat (0 mismatches),
including the `đ`/`dj` name case and the small-boat bench layout.
