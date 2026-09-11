<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();
no_cache_headers();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>PillowPotion — Redirects</title>
<style>
  * { box-sizing: border-box; }
  html, body { overflow-x: hidden; }
  body {
    margin: 0; min-height: 100vh; padding: 32px 24px;
    background: #f8fafc; color: #0f172a;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }
  .topbar { max-width: 1400px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: space-between; }
  .brand { font-size: 22px; font-weight: 800; color: #7e22ce; }
  .btn-add {
    background: #7e22ce; color: #fff; border: none; font-weight: 700;
    padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 13.5px;
  }

  .cards-grid {
    max-width: 1400px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
  }
  @media (max-width: 1200px) { .cards-grid { grid-template-columns: repeat(3, 1fr); } }
  @media (max-width: 900px)  { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 560px)  { .cards-grid { grid-template-columns: 1fr; } }

  .rd-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 16px; display: flex; flex-direction: column; gap: 10px;
  }
  .rd-slug { font-size: 15px; font-weight: 700; font-family: 'SF Mono', monospace; }
  .rd-link { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: #f5f3ff; border-radius: 8px; border: 1px solid #ddd6fe; }
  .rd-link-k { font-size: 9.5px; font-weight: 700; color: #7e22ce; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px; }
  .rd-link-k.dest { color: #92400e; }
  .rd-link-text { min-width: 0; font-size: 11.5px; font-family: 'SF Mono', monospace; color: #6b21a8; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rd-link-text.dest { color: #92400e; }
  .rd-link.dest { background: #fef3c7; border-color: #fcd34d; }
  .rd-copy { background: #fff; border: 1px solid #ddd6fe; cursor: pointer; padding: 5px; border-radius: 6px; color: #6b21a8; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
  .rd-copy.dest { border-color: #fcd34d; color: #92400e; }
  .rd-copy svg { width: 13px; height: 13px; }

  .rd-actions { display: flex; gap: 4px; justify-content: flex-end; }
  .rd-actions button { background: rgba(255,255,255,0.95); border: 1px solid #e2e8f0; cursor: pointer; padding: 6px; border-radius: 7px; color: #64748b; }
  .rd-actions button:hover { background: #f5f3ff; color: #7e22ce; border-color: #ddd6fe; }
  .rd-actions button.del:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
  .rd-actions svg { width: 13px; height: 13px; }

  .modal-back { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: none; align-items: center; justify-content: center; z-index: 100; padding: 24px; }
  .modal-back.open { display: flex; }
  .modal { background: #fff; border-radius: 16px; padding: 28px; width: 480px; max-width: 100%; }
  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
  .modal-header h2 { margin: 0; font-size: 20px; }
  .btn-close { background: none; border: none; cursor: pointer; color: #64748b; }
  .field { margin-bottom: 14px; }
  .field-label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
  .field-label .req { color: #ef4444; }
  .field-label .hint { font-weight: 400; color: #94a3b8; font-size: 11px; }
  .field input { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff; font-family: 'SF Mono', monospace; }
  .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }
  .btn-secondary { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
  .btn-primary { background: #7e22ce; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; cursor: pointer; font-weight: 700; }

  .grid-empty-msg { text-align: center; padding: 60px; color: #94a3b8; grid-column: 1 / -1; }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">PillowPotion — Redirects</div>
  <button class="btn-add" onclick="openModal()">+ Add Redirect</button>
</div>

<div id="grid" class="cards-grid"></div>

<div class="modal-back" id="rd-modal" onclick="if(event.target===this) closeModal()">
  <div class="modal">
    <div class="modal-header">
      <h2 id="rd-modal-title">Add Redirect</h2>
      <button class="btn-close" onclick="closeModal()">✕</button>
    </div>
    <div class="field">
      <label class="field-label" for="rd-slug">Source Slug<span class="req">*</span> <span class="hint">(used in pillowpotion.net/&lt;slug&gt;)</span></label>
      <input id="rd-slug" type="text" placeholder="e.g. rp">
    </div>
    <div class="field">
      <label class="field-label" for="rd-dest">Destination URL<span class="req">*</span></label>
      <input id="rd-dest" type="text" placeholder="e.g. rushpermit.com">
    </div>
    <div class="modal-actions">
      <button class="btn-secondary" onclick="closeModal()">Cancel</button>
      <button class="btn-primary" onclick="submitForm()">Save</button>
    </div>
  </div>
</div>

<script>
const DOMAIN = 'https://pillowpotion.net';
let _cache = [];
let _editingSlug = null;

function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function $(id) { return document.getElementById(id); }

async function load() {
  const r = await fetch('redirects-api.php');
  _cache = await r.json();
  render();
}

function render() {
  const grid = $('grid');
  if (!_cache.length) {
    grid.innerHTML = '<div class="grid-empty-msg">No redirects yet — click "+ Add Redirect" to create one.</div>';
    return;
  }
  grid.innerHTML = _cache.map(renderCard).join('');
}

function renderCard(r) {
  return `
    <div class="rd-card">
      <div class="rd-slug">/${esc(r.slug)}</div>
      <div class="rd-link">
        <div style="flex:1;min-width:0;">
          <div class="rd-link-k">Traffic Link</div>
          <div class="rd-link-text">pillowpotion.net/${esc(r.slug)}</div>
        </div>
        <button class="rd-copy" onclick="copyLink('${esc(r.slug)}', this)" title="Copy traffic link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
      </div>
      <div class="rd-link dest">
        <div style="flex:1;min-width:0;">
          <div class="rd-link-k dest">Destination URL</div>
          <div class="rd-link-text dest">${esc(r.destination)}</div>
        </div>
        <button class="rd-copy dest" onclick="copyDest('${esc(r.slug)}', this)" title="Copy destination link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
      </div>
      <div class="rd-actions">
        <button onclick="editRedirect('${esc(r.slug)}')" title="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="del" onclick="deleteRedirect('${esc(r.slug)}')" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
      </div>
    </div>
  `;
}

function openModal(r) {
  _editingSlug = r ? r.slug : null;
  $('rd-modal-title').textContent = r ? 'Edit Redirect' : 'Add Redirect';
  $('rd-slug').value = r ? r.slug : '';
  $('rd-slug').readOnly = !!r;
  $('rd-dest').value = r ? r.destination : '';
  $('rd-modal').classList.add('open');
}
function closeModal() { $('rd-modal').classList.remove('open'); _editingSlug = null; }

async function submitForm() {
  const slug = $('rd-slug').value.trim();
  const destination = $('rd-dest').value.trim();
  if (!slug) { alert('Source slug required'); return; }
  if (!destination) { alert('Destination URL required'); return; }
  const payload = _editingSlug
    ? { _action: 'update', slug: _editingSlug, destination }
    : { _action: 'create', slug, destination };
  try {
    const r = await fetch('redirects-api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    if (!r.ok) { const e = await r.json().catch(() => ({})); throw new Error(e.error || ('HTTP ' + r.status)); }
    closeModal();
    await load();
  } catch (e) { alert('Save failed: ' + e.message); }
}

function editRedirect(slug) {
  const r = _cache.find(x => x.slug === slug);
  if (r) openModal(r);
}

async function deleteRedirect(slug) {
  if (!confirm('Delete this redirect?')) return;
  await fetch('redirects-api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ _action: 'delete', slug }) });
  await load();
}

function copyLink(slug, btn) {
  navigator.clipboard.writeText(DOMAIN + '/' + slug).catch(() => {});
  flash(btn);
}
function copyDest(slug, btn) {
  const r = _cache.find(x => x.slug === slug);
  if (!r) return;
  navigator.clipboard.writeText(r.destination).catch(() => {});
  flash(btn);
}
function flash(btn) {
  if (!btn) return;
  const original = btn.innerHTML;
  btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
  setTimeout(() => { btn.innerHTML = original; }, 1200);
}

load();
</script>
</body>
</html>
