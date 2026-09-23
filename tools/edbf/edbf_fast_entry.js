/* ============================================================================
 * EDBF free crew lists — FAST ENTRY console tool
 * ----------------------------------------------------------------------------
 * Reverse-engineered from https://edbf.idbfchamps.org/CrewListsFree.php
 *
 * HOW IT WORKS (why this is fast and reliable):
 *   - The whole athlete roster (id + name + gender + category + paddling side)
 *     is embedded in the page's pool tables (#Left_table / #Right_table). No
 *     lookups or filtering needed — every athlete is addressable by `idsb`.
 *   - Placement is done by the page's own pool-row click handler. The boat
 *     SEAT widgets ignore scripted events (they need real OS clicks), BUT the
 *     pool rows are plain HTML and DO respond to synthetic mouse events. So we
 *     drive placement from the POOL side, not the seat side.
 *   - A seat is "selected" by setting the page global `selPos` to the seat's
 *     jQuery object (exactly what a real click sets). Then firing
 *     mousedown/mouseup/click on the athlete's pool row drops them into it.
 *   - Team Captain is a native <select name="captain"> whose option VALUES are
 *     athlete ids; we set it to the helm's id.
 *   - NOTHING is written to the server until saveCL() runs. Placing, selecting,
 *     dry-runs — all client-side. Reloading the page discards everything.
 *
 * USAGE (in the browser console, on the crew-list page):
 *   1. Select the correct class/race so its (empty) seats render.
 *   2. Paste this whole file. It defines edbfDryRun() and edbfFill().
 *   3. edbfDryRun(crew)            // resolves every athlete -> id + seat, PLACES NOTHING
 *   4. edbfFill(crew)              // places everyone, sets captain, DOES NOT SAVE
 *   5. eyeball the boat, then:
 *      edbfFill(crew, {save:true}) // places + saves (saveCL). Refuses to save if
 *                                  // any athlete is unresolved.
 *   To abort at any point without consequences: reload the page.
 *
 * CREW FORMAT (same shape as munich_serbia_crews.json entries):
 *   const crew = {
 *     name: 'SM BCP Open 2000m',
 *     athletes: [
 *       { role:'drummer', side:null,    row:null, name:'Maja Marković' },
 *       { role:'paddler', side:'left',  row:1,    name:'Snežana Šumar Nelić' },
 *       { role:'paddler', side:'right', row:1,    name:'...' },
 *       { role:'helm',    side:null,    row:null, name:'Nela Cajković' },
 *       { role:'reserve', side:null,    row:null, name:'...' },
 *       ...
 *     ]
 *   };
 * ==========================================================================*/
(function () {
  'use strict';
  var $ = window.jQuery;
  if (!$) { console.error('jQuery not found on page — are you on the crew-list page?'); return; }

  // --- name normalization -------------------------------------------------
  // Order-independent key so "First Last" (your data) matches "Last First"
  // (EDBF). Strips diacritics; maps Serbian đ/Đ -> "dj" so "Đurić" == "Djurić".
  function norm(s) {
    s = (s == null ? '' : String(s));
    s = s.replace(/đ/g, 'dj').replace(/Đ/g, 'dj');
    s = s.normalize('NFD').replace(/[̀-ͯ]/g, ''); // drop combining marks
    s = s.toLowerCase().replace(/[^a-z\s]/g, ' ').replace(/\s+/g, ' ').trim();
    return s;
  }
  function nameKey(s) {
    return norm(s).split(' ').filter(Boolean).sort().join(' ');
  }

  // --- roster map: nameKey -> idsb ----------------------------------------
  function buildRoster() {
    var map = new Map();
    var dupes = [];
    document.querySelectorAll('#Left_table tr[idsb], #Right_table tr[idsb]').forEach(function (tr) {
      var idsb = tr.getAttribute('idsb');
      if (!idsb || idsb === '*header*') return;
      var ln = tr.getAttribute('last_name') || '';
      var fn = tr.getAttribute('first_name') || '';
      var k = nameKey(ln + ' ' + fn);
      if (map.has(k) && map.get(k) !== idsb) dupes.push(k);
      map.set(k, idsb);
    });
    if (dupes.length) console.warn('Roster has duplicate name keys (disambiguate by hand):', dupes);
    return map;
  }

  // Resolve an athlete to their EDBF id:
  //   1) explicit edbfId/id from the data (most robust)
  //   2) exact normalized-name match
  //   3) unique subset match — app names sometimes drop a surname token
  //      (e.g. "Jelena Zajeganović" vs EDBF "Zajeganović Jakovljević Jelena").
  //      Only used when exactly ONE roster entry contains all the app tokens.
  function idOf(a, roster) {
    if (a.edbfId) return a.edbfId;
    if (a.id) return a.id;
    var key = nameKey(a.name);
    if (roster.has(key)) return roster.get(key);
    var toks = key.split(' ').filter(Boolean);
    if (!toks.length) return null;
    var hits = [];
    roster.forEach(function (id, k) {
      var kt = k.split(' ');
      if (toks.every(function (t) { return kt.indexOf(t) >= 0; })) hits.push(id);
    });
    if (hits.length === 1) { console.warn('matched "' + a.name + '" by subset -> id ' + hits[0] + ' (verify)'); return hits[0]; }
    return null;
  }

  // --- seat cells in the currently loaded class ---------------------------
  function seatCells() {
    var q = function (p) { return Array.prototype.slice.call(document.querySelectorAll('td[pos="' + p + '"]')); };
    return { dr: q('dr')[0], he: q('he')[0], lt: q('lt'), rt: q('rt'), res: q('res') };
  }
  // bench (row number) lives on the seat's <tr>, not the <td>
  function benchOf(td) {
    var b = td.getAttribute('bench');
    if (b == null) { var tr = td.closest('tr'); b = tr ? tr.getAttribute('bench') : null; }
    return parseInt(b, 10);
  }

  // athlete -> seat <td>. Reserves are assigned to empty res slots in order.
  function seatFor(a, cells, usedRes) {
    if (a.role === 'drummer') return cells.dr;
    if (a.role === 'helm') return cells.he;
    if (a.role === 'reserve') {
      for (var i = 0; i < cells.res.length; i++) {
        if (usedRes.indexOf(i) === -1) { usedRes.push(i); return cells.res[i]; }
      }
      return null;
    }
    var list = (a.side === 'right') ? cells.rt : cells.lt;
    for (var j = 0; j < list.length; j++) {
      if (benchOf(list[j]) === (a.row - 1)) return list[j];
    }
    return null;
  }

  // --- placement primitive ------------------------------------------------
  function fireRow(idsb) {
    var row = document.querySelector('#Left_table tr[idsb="' + idsb + '"], #Right_table tr[idsb="' + idsb + '"]');
    if (!row) return false;
    var cell = row.cells[2] || row;
    ['mousedown', 'mouseup', 'click'].forEach(function (t) {
      row.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window }));
      cell.dispatchEvent(new MouseEvent(t, { bubbles: true, cancelable: true, view: window }));
    });
    return true;
  }
  function placeInSeat(td, idsb) {
    window.selPos = $(td);
    try { td.style.backgroundColor = 'rgb(255,255,0)'; } catch (e) {}
    return fireRow(idsb);
  }

  function seatLabel(td) {
    if (!td) return 'NO SEAT';
    var b = benchOf(td);
    return td.getAttribute('pos') + (isNaN(b) ? '' : ('#' + b));
  }

  // --- DRY RUN: resolve everything, place nothing -------------------------
  window.edbfDryRun = function (crew) {
    var roster = buildRoster(), cells = seatCells(), usedRes = [];
    if (!cells.lt.length && !cells.dr) { console.error('No seats found — select a class/race first.'); return; }
    var rep = crew.athletes.map(function (a) {
      var idsb = idOf(a, roster);
      var td = seatFor(a, cells, usedRes);
      return {
        name: a.name, role: a.role, side: a.side || '', row: a.row == null ? '' : a.row,
        idsb: idsb || '⚠ NOT FOUND', seat: seatLabel(td)
      };
    });
    console.table(rep);
    var bad = rep.filter(function (r) { return /NOT FOUND/.test(r.idsb) || r.seat === 'NO SEAT'; });
    console.log(bad.length ? ('⚠ ' + bad.length + ' unresolved — fix names/seats before edbfFill') : '✓ all ' + rep.length + ' resolved');
    return rep;
  };

  // --- FILL: place everyone; save only if opts.save ------------------------
  window.edbfFill = function (crew, opts) {
    opts = opts || {};
    var roster = buildRoster(), cells = seatCells(), usedRes = [];
    if (!cells.lt.length && !cells.dr) { console.error('No seats found — select a class/race first.'); return; }

    // auto-accept native OK/confirm popups so they cannot block us
    var origC = window.confirm, origA = window.alert;
    window.confirm = function () { return true; };
    window.alert = function () {};

    // place paddlers/drummer/helm first, reserves last (so res slots are stable)
    var order = crew.athletes.slice().sort(function (a, b) {
      return (a.role === 'reserve' ? 1 : 0) - (b.role === 'reserve' ? 1 : 0);
    });

    var placed = 0, fail = [];
    order.forEach(function (a) {
      var idsb = idOf(a, roster);
      var td = seatFor(a, cells, usedRes);
      if (idsb && td) { if (placeInSeat(td, idsb)) placed++; else fail.push(a.name + ' [click failed]'); }
      else fail.push(a.name + (idsb ? '' : ' [no id]') + (td ? '' : ' [no seat]'));
    });

    // captain = Helm (option values are athlete ids)
    var helm = crew.athletes.filter(function (a) { return a.role === 'helm'; })[0];
    if (helm) {
      var hid = idOf(helm, roster);
      var sel = document.querySelector('select[name="captain"]');
      if (sel && hid) { sel.value = hid; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      else console.warn('Could not set captain=Helm (select or helm id missing).');
    }

    window.confirm = origC; window.alert = origA;

    console.log('placed ' + placed + '/' + crew.athletes.length + (fail.length ? (' — unresolved: ' + JSON.stringify(fail)) : ' — all good'));
    if (opts.save) {
      if (fail.length) { console.warn('⛔ NOT saving — resolve the above first, or place them by hand then call saveCL().'); }
      else { window.saveCL(); console.log('✅ saveCL() called — crew saved.'); }
    } else {
      console.log('ℹ️ Not saved. Verify the boat, then edbfFill(crew,{save:true}). Reload to discard.');
    }
    return { placed: placed, fail: fail };
  };

  console.log('%cEDBF fast-entry loaded.', 'font-weight:bold');
  console.log('Steps:  edbfDryRun(crew)  →  edbfFill(crew)  →  edbfFill(crew,{save:true})');
})();
