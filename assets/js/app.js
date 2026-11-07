/* =====================================================================
   Task Reminder System — client behaviour
   - Reminder dispatcher polling (FR-005, NFR-001: every 5 s)
   - Tier-specific audio + vibration execution (FR-002)
   - Mandatory acknowledgment for High/Critical (FR-003, B-03)
   - Confirm dialogs for complete / delete (B-04, B-05) and profile sheet (B-06)
   ===================================================================== */
(function () {
  'use strict';
  const body = document.body;
  const BASE = body.dataset.base || '';
  const CSRF = body.dataset.csrf || '';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------------- toasts ---------------- */
  function toast(msg, ms = 3500) {
    const stack = $('#toast-stack');
    if (!stack) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    stack.appendChild(t);
    setTimeout(() => t.remove(), ms);
  }
  $$('[data-autohide]').forEach((el) => {
    const keep = el.querySelector('form') ? 15000 : 8000;
    setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, keep);
  });

  /* ---------------- dialogs ---------------- */
  let lastFocus = null;
  function openDialog(el) {
    lastFocus = document.activeElement;
    el.hidden = false;
    const f = el.querySelector('button:not([disabled]), a[href], input:not([type=hidden])');
    if (f) setTimeout(() => f.focus(), 30);
  }
  function closeDialog(el) {
    el.hidden = true;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-open]');
    if (opener) { openDialog(document.getElementById(opener.dataset.open)); return; }
    const closer = e.target.closest('[data-close]');
    if (closer) { closeDialog(closer.closest('.modal-backdrop, .sheet-backdrop')); return; }
    if (e.target.matches('.sheet-backdrop, #confirm-modal')) closeDialog(e.target);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = $$('.sheet-backdrop:not([hidden]), #confirm-modal:not([hidden])');
    if (open.length) closeDialog(open[open.length - 1]);
  });

  /* Confirm complete / delete */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-confirm]');
    if (!b) return;
    const m = $('#confirm-modal');
    const kind = b.dataset.confirm;
    $('#confirm-form').action = BASE + '/task_action.php';
    $('#confirm-task').value = b.dataset.task;
    $('#confirm-action').value = kind;
    const ok = $('#confirm-ok');
    const icon = $('#confirm-icon');
    if (kind === 'delete') {
      $('#confirm-title').textContent = 'Delete this task?';
      $('#confirm-text').textContent = '"' + b.dataset.title + '" and its reminder will be removed. This cannot be undone.';
      ok.textContent = 'Delete task';
      ok.className = 'btn btn-danger btn-block';
      icon.className = 'modal-icon danger';
    } else {
      $('#confirm-title').textContent = 'Mark as completed?';
      $('#confirm-text').textContent = '"' + b.dataset.title + '" moves to Completed. You can undo this right after. High and Critical alerts must be acknowledged first.';
      ok.textContent = 'Yes, mark completed';
      ok.className = 'btn btn-primary btn-block';
      icon.className = 'modal-icon';
    }
    openDialog(m);
  });

  /* Password toggle */
  $$('[data-toggle-pw]').forEach((b) => b.addEventListener('click', () => {
    const i = document.getElementById(b.dataset.togglePw);
    const show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));

  /* ---------------- audio + vibration engine (FR-002) ---------------- */
  const Audio = (function () {
    let ctx = null;
    function context() {
      if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        ctx = new AC();
      }
      return ctx;
    }
    function note(c, freq, start, dur, type, vol) {
      const o = c.createOscillator();
      const g = c.createGain();
      o.type = type;
      o.frequency.value = freq;
      g.gain.setValueAtTime(0.0001, start);
      g.gain.exponentialRampToValueAtTime(vol, start + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
      o.connect(g).connect(c.destination);
      o.start(start);
      o.stop(start + dur + 0.05);
    }
    const TONES = {
      'Soft chime':  [[880, 0, 0.45, 'sine']],
      'Gentle bell': [[660, 0, 0.5, 'sine'], [880, 0.28, 0.7, 'sine']],
      'Clear chime': [[1046, 0, 0.3, 'triangle'], [1318, 0.18, 0.3, 'triangle'], [1046, 0.36, 0.45, 'triangle']],
      'Urgent tone': [[880, 0, 0.14, 'square'], [660, 0.16, 0.14, 'square'], [880, 0.32, 0.14, 'square'], [660, 0.48, 0.14, 'square'], [880, 0.64, 0.14, 'square'], [660, 0.8, 0.2, 'square']],
      'Alarm siren': [[520, 0, 0.18, 'sawtooth'], [700, 0.18, 0.18, 'sawtooth'], [880, 0.36, 0.18, 'sawtooth'], [1040, 0.54, 0.3, 'sawtooth']],
    };
    function play(tone, decibels) {
      const c = context();
      if (!c) return false;
      if (c.state === 'suspended') c.resume();
      // Map the profile's decibel limit (40-100 dB) to gain 0.04-0.6
      const db = Math.max(40, Math.min(100, decibels || 60));
      const vol = 0.04 + ((db - 40) / 60) * 0.56;
      const t0 = c.currentTime + 0.02;
      (TONES[tone] || TONES['Soft chime']).forEach(([f, s, d, type]) => note(c, f, t0 + s, d, type, (type === 'square' || type === 'sawtooth') ? vol * 0.5 : vol));
      return c.state === 'running';
    }
    function suspended() { const c = context(); return !c || c.state !== 'running'; }
    function unlock() { const c = context(); if (c && c.state === 'suspended') return c.resume(); return Promise.resolve(); }
    return { play, suspended, unlock };
  })();
  const VIB = { 'Short pulse': [200], 'Double pulse': [180, 120, 180], 'Repeating pulse': [400, 200, 400, 200, 400], 'Rapid pulse': [120, 60, 120, 60, 120, 60, 120, 60, 120, 60, 120], 'Long buzz': [800] };
  function vibrate(p) { if (navigator.vibrate) { try { navigator.vibrate(VIB[p] || [200]); } catch (e) { /* unsupported */ } } }

  /* Admin previews */
  $$('[data-preview-tone]').forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault();
    const r = $('#db-range');
    Audio.play(b.dataset.previewTone, r ? +r.value : 70);
  }));
  $$('[data-preview-vib]').forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault();
    vibrate(b.dataset.previewVib);
    b.classList.add('shake'); setTimeout(() => b.classList.remove('shake'), 450);
    if (!navigator.vibrate) toast('This device has no vibration motor. Phones will vibrate.');
  }));
  const range = $('#db-range');
  if (range) range.addEventListener('input', () => { $('#db-out').textContent = range.value; });
  const pf = $('#profile-form');
  if (pf) pf.addEventListener('change', () => { const c = $('#dirty-chip'); if (c) c.hidden = false; });

  /* ---------------- task form helpers (screens 03-05) ---------------- */
  const tf = $('#task-form');
  if (tf) {
    const title = tf.elements.title, due = tf.elements.due_date, rem = tf.elements.reminder_time;
    const pad = (n) => String(n).padStart(2, '0');
    const toLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    const hint = $('#priority-hint');
    const HINT = {
      NOT_REQUESTED: 'Low reminders can be dismissed without acknowledgment.',
      OPTIONAL: 'Normal reminders can be dismissed or acknowledged.',
      REQUIRED: 'This priority requires explicit acknowledgment. The alert stays active until you acknowledge it.',
    };
    function priority() { return tf.querySelector('input[name=priority]:checked'); }
    function check() {
      const p = priority();
      if (p) hint.textContent = `${p.dataset.audio} + ${p.dataset.vib.toLowerCase()}. ${HINT[p.dataset.ack] || ''}`;
      const now = Date.now();
      const d = due.value ? new Date(due.value).getTime() : NaN;
      const r = rem.value ? new Date(rem.value).getTime() : NaN;
      const ok = title.value.trim().length >= 3 && d > now && r < d && r >= now - 60000 && !!p;
      const box = $('#ready-box');
      box.hidden = !ok;
      if (ok) $('#ready-text').textContent = `${p.value} priority · ${p.dataset.audio} + ${p.dataset.vib.toLowerCase()}. Acknowledgment ${p.dataset.ack === 'REQUIRED' ? 'required' : p.dataset.ack === 'OPTIONAL' ? 'optional' : 'not requested'}.`;
      // Clear an error as soon as the field becomes valid (screen 05 "Corrected information")
      [[title, title.value.trim().length >= 3], [due, d > now], [rem, r < d && r >= now - 60000]].forEach(([el, valid]) => {
        const f = el.closest('.field');
        if (valid && f.classList.contains('has-error')) { f.classList.remove('has-error'); el.setAttribute('aria-invalid', 'false'); const m = f.querySelector('.field-msg'); if (m) m.textContent = ''; }
      });
      if (p) { const f = p.closest('.field'); if (f.classList.contains('has-error')) { f.classList.remove('has-error'); $('#e-pri').textContent = ''; } }
    }
    tf.addEventListener('input', check);
    tf.addEventListener('change', check);
    $$('[data-remind-in]').forEach((b) => b.addEventListener('click', () => {
      const r = new Date(Date.now() + (+b.dataset.remindIn) * 60000);
      rem.value = toLocal(r);
      if (!due.value || new Date(due.value) <= r) due.value = toLocal(new Date(r.getTime() + 60 * 60000));
      check();
    }));
    $$('[data-remind-before]').forEach((b) => b.addEventListener('click', () => {
      if (!due.value) { toast('Choose the due date and time first.'); due.focus(); return; }
      rem.value = toLocal(new Date(new Date(due.value).getTime() - (+b.dataset.remindBefore) * 60000));
      check();
    }));
    check();
    const es = $('#error-summary');
    if (es) es.focus();
  }

  /* ---------------- reminder dispatcher + alert modal ---------------- */
  if (body.dataset.poll !== '1') return;

  const modal = $('#alert-modal');
  const card = $('#alert-card');
  const POLL = +body.dataset.pollMs || 5000;
  let queue = [];
  let current = null;       // alert being shown
  let state = 'ringing';    // ringing | handled
  let repeatTimer = null;
  const handledLocally = new Set();
  let soundPill = null;

  const BELL = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 1112 0v5l1.5 2h-15L6 16z"/><path d="M10 20a2 2 0 004 0"/></svg>';
  const CHECK = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  const AUD = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4h4l5 4V6L8 10H4z"/><path d="M16 9a4 4 0 010 6"/></svg>';
  const VIBI = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="4" width="8" height="16" rx="2"/><path d="M4 9v6M20 9v6"/></svg>';

  function signal(a) {
    Audio.play(a.profile.audio, a.profile.decibels);
    vibrate(a.profile.vibration);
    showSoundPill();
  }
  function showSoundPill() {
    if (!Audio.suspended()) { if (soundPill) { soundPill.remove(); soundPill = null; } return; }
    if (soundPill) return;
    soundPill = document.createElement('button');
    soundPill.className = 'sound-pill';
    soundPill.type = 'button';
    soundPill.innerHTML = AUD + ' Tap to enable alert sound';
    soundPill.addEventListener('click', () => Audio.unlock().then(() => { if (current && state === 'ringing') signal(current); soundPill.remove(); soundPill = null; }));
    document.body.appendChild(soundPill);
  }

  function render(a) {
    const tier = a.priority.toLowerCase();
    const required = a.profile.ack === 'REQUIRED';
    const optional = a.profile.ack === 'OPTIONAL';
    card.className = 'modal modal-alert tier-' + tier + (state === 'ringing' ? ' is-ringing' : '');
    const today = new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }).toUpperCase();

    if (state === 'handled') {
      // Screen 12 · Acknowledged, not completed
      card.innerHTML = `
        <div class="alert-head"><h2 id="alert-title">${a.handled === 'dismissed' ? 'Reminder dismissed' : 'Alert acknowledged'}</h2></div>
        <div class="bell" style="background:var(--ok-bg);color:var(--ok);box-shadow:0 0 0 10px #f2faf5">${CHECK}</div>
        <p class="alert-big">${a.handled === 'dismissed' ? 'Dismissed.' : 'Silenced.'} Task still pending.</p>
        <p class="muted">${a.handled === 'dismissed' ? 'Dismissing' : 'Acknowledging'} handles the alert. It does not complete <b>${esc(a.title)}</b>.</p>
        <div class="alert-status"><span>✓ Reminder sent · ${esc(a.sent_label)}</span><span>✓ ${a.handled === 'dismissed' ? 'Dismissed' : 'Acknowledgment recorded'} · ${esc(a.handled_at || '')}</span></div>
        <a class="btn btn-primary btn-block" href="${BASE}/task_view.php?id=${a.task_id}">Open task</a>
        <button class="btn btn-soft btn-block" type="button" data-alert="close">${queue.length ? 'Next alert (' + queue.length + ')' : 'Back to workspace'}</button>`;
      return;
    }

    const big = required ? 'Your attention is needed.' : 'A timely nudge.';
    const sub = required ? 'This alert remains active until you explicitly acknowledge it.' : 'A reminder to check hardware deliveries and stock.';
    const headTitle = required ? 'Active alert' : 'Delivery &amp; stock alert';
    let actions = '';
    if (required) {
      actions = `<p class="alert-hint">Silencing the alert does not complete the task.</p>
        <button class="btn ${tier === 'critical' ? 'btn-danger' : 'btn-primary'} btn-block" type="button" data-alert="acknowledge">✓ Acknowledge &amp; silence</button>`;
    } else if (optional) {
      actions = `<p class="alert-hint">Dismissal returns you to your workspace. The task remains pending.</p>
        <button class="btn btn-primary btn-block" type="button" data-alert="dismiss">Dismiss reminder</button>
        <button class="btn btn-soft btn-block" type="button" data-alert="acknowledge">Acknowledge instead</button>`;
    } else {
      actions = `<p class="alert-hint">Dismissal returns you to your workspace. The task remains pending.</p>
        <button class="btn btn-primary btn-block" type="button" data-alert="dismiss">Dismiss reminder</button>`;
    }
    // B-03: no back/close control on mandatory alerts
    const close = required ? '' : `<button class="icon-btn" type="button" data-alert="dismiss" aria-label="Dismiss reminder" style="margin:0 -10px 0 0"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6l12 12M18 6L6 18"/></svg></button>`;
    card.innerHTML = `
      <div class="alert-head"><div><h2 id="alert-title">${headTitle}</h2><p class="alert-overline">${esc(today)}</p></div>${close}</div>
      <div class="bell">${BELL}</div>
      <div><span class="chip p-${tier}">${esc(a.priority)} priority</span></div>
      <p class="alert-big">${big}</p>
      <p class="muted small">${sub}</p>
      <div class="card alert-task">
        <h3 class="h-title">${esc(a.title)}</h3>
        <dl class="kv-list"><div><dt>Due</dt><dd>${esc(a.due_label)}</dd></div><div><dt>Task status</dt><dd>${esc(a.status)}</dd></div></dl>
      </div>
      <div class="signal-row"><span class="signal" title="${required ? 'Audio active' : 'Audio played'}">${AUD} ${esc(a.profile.audio)}</span><span class="signal" title="${required ? 'Vibration active' : 'Vibration sent'}">${VIBI} ${esc(a.profile.vibration)}</span></div>
      <div class="alert-status"><span>✓ Reminder sent · ${esc(a.sent_label)} · ${required ? 'audio and vibration active' : 'audio played, vibration sent'}</span>
        ${required ? '<span class="warn">⚠ Not acknowledged · Alert still active</span>' : `<span>${optional ? 'Acknowledgment is optional in this profile.' : 'This profile does not request acknowledgment.'}</span>`}</div>
      ${actions}`;
  }

  function show(a) {
    current = a;
    state = 'ringing';
    render(a);
    if (modal.hidden) { lastFocus = document.activeElement; modal.hidden = false; }
    const first = card.querySelector('[data-alert]:not(.icon-btn)') || card.querySelector('[data-alert]');
    if (first) setTimeout(() => first.focus(), 30);
    signal(a);
    clearInterval(repeatTimer);
    if (a.profile.ack === 'REQUIRED') {
      repeatTimer = setInterval(() => { if (current === a && state === 'ringing') signal(a); }, 4000);
    }
  }

  function next() {
    clearInterval(repeatTimer);
    current = null;
    const a = queue.shift();
    if (a) { show(a); } else { modal.hidden = true; if (soundPill) { soundPill.remove(); soundPill = null; } }
  }

  async function act(action) {
    const a = current;
    if (!a) return;
    try {
      const res = await fetch(BASE + '/api/reminder_action.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ reminder_id: a.reminder_id, action }),
      });
      const data = await res.json();
      if (!data.ok) { toast(data.error || 'Could not update the reminder.'); nudge(); return; }
      handledLocally.add(a.reminder_id);
      clearInterval(repeatTimer);
      a.handled = data.state;
      a.handled_at = data.at;
      state = 'handled';
      render(a);
      const btn = card.querySelector('.btn');
      if (btn) btn.focus();
    } catch (e) {
      toast('Connection problem. The alert stays active; try again.');
    }
  }

  function nudge() {
    card.classList.remove('shake');
    void card.offsetWidth;
    card.classList.add('shake');
    toast('Acknowledge this alert to continue.');
  }

  card.addEventListener('click', (e) => {
    const b = e.target.closest('[data-alert]');
    if (!b) return;
    const what = b.dataset.alert;
    if (what === 'close') { next(); if (!current && /task_view\.php|dashboard\.php/.test(location.pathname)) location.reload(); return; }
    act(what);
  });
  modal.addEventListener('click', (e) => {
    if (e.target !== modal || !current) return;
    if (state === 'handled') { next(); return; }
    if (current.profile.ack === 'REQUIRED') nudge(); // B-03 / FR-003: cannot click away
  });
  document.addEventListener('keydown', (e) => {
    if (modal.hidden || !current) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      if (state === 'handled') next();
      else if (current.profile.ack === 'REQUIRED') nudge();
      else act('dismiss');
    }
    if (e.key === 'Tab') { // keep focus inside the alert
      const f = $$('button, a[href]', card);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });
  document.addEventListener('pointerdown', () => { if (Audio.suspended()) Audio.unlock().then(showSoundPill); }, { passive: true });

  async function poll() {
    try {
      const res = await fetch(BASE + '/api/poll.php', { credentials: 'same-origin', cache: 'no-store' });
      if (res.status === 401) return;
      const data = await res.json();
      if (!data.ok) return;
      const fresh = data.alerts.filter((a) => !handledLocally.has(a.reminder_id) && (!current || current.reminder_id !== a.reminder_id));
      const known = new Set(queue.map((q) => q.reminder_id));
      fresh.forEach((a) => { if (!known.has(a.reminder_id)) queue.push(a); });
      // A Critical alert pre-empts a lower one that is still ringing
      if (current && state === 'ringing' && queue.length) {
        const rank = { Critical: 1, High: 2, Normal: 3, Low: 4 };
        queue.sort((x, y) => rank[x.priority] - rank[y.priority]);
        if (rank[queue[0].priority] < rank[current.priority] && current.profile.ack !== 'REQUIRED') { queue.push(current); show(queue.shift()); }
      }
      if (!current && queue.length) show(queue.shift());
    } catch (e) { /* offline: try again next tick */ }
  }
  poll();
  setInterval(poll, POLL);
})();
