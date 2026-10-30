const API = 'api';

const state = {
  incomes: [],
  payments: [],
  summary: { totalCharityPKR: 0, totalSentPKR: 0, availablePKR: 0 },
  currentRate: null,
};

const $ = (sel) => document.querySelector(sel);
const fmtUSD = (n) => '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtPKR = (n) => 'Rs ' + Number(n).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
const fmtDate = (iso) => {
  const d = new Date(iso + 'T00:00:00');
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};
const todayISO = () => new Date().toISOString().slice(0, 10);

function showToast(msg) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => t.classList.remove('show'), 2400);
}

function setSyncStatus(online) {
  const dot = $('#syncDot');
  const label = $('#syncLabel');
  dot.classList.toggle('offline', !online);
  label.textContent = online ? 'Synced' : 'Offline';
}

/* ---------- Data loading ---------- */
async function loadAll() {
  try {
    const [incomes, payments, summary] = await Promise.all([
      fetch(`${API}/income.php`).then(r => r.json()),
      fetch(`${API}/payments.php`).then(r => r.json()),
      fetch(`${API}/summary.php`).then(r => r.json()),
    ]);
    state.incomes = incomes;
    state.payments = payments;
    state.summary = summary;
    setSyncStatus(true);
    render();
  } catch (e) {
    setSyncStatus(false);
    console.error(e);
  }
}

/* ---------- Render ---------- */
function render() {
  const available = state.summary.availablePKR;
  $('#availableAmount').textContent = fmtPKR(available);
  $('#availableAmount').classList.toggle('negative', available < 0);
  $('#totalCharity').textContent = fmtPKR(state.summary.totalCharityPKR);
  $('#totalSent').textContent = fmtPKR(state.summary.totalSentPKR);

  const incomeList = $('#incomeList');
  incomeList.innerHTML = state.incomes.length ? '' : '<div class="empty">No income entries yet — tap "Add Income" to start</div>';
  state.incomes.forEach(entry => {
    const row = document.createElement('div');
    row.className = 'row-card';
    row.innerHTML = `
      <button class="row-delete" data-type="income" data-id="${entry.id}" title="Delete">✕</button>
      <div class="row-top"><span class="name">${escapeHtml(entry.sourceName)}</span><span class="date">${fmtDate(entry.date)}</span></div>
      <div class="row-usd">Balance: ${fmtUSD(entry.amountUSD)}</div>
      <div class="row-charity">
        <span class="chip">${Number(entry.charityPercent)}% = ${fmtUSD(entry.charityUSD)}</span>
        <span class="chip pkr">${fmtPKR(entry.charityPKR)} @ ${Number(entry.exchangeRate).toFixed(2)}</span>
      </div>`;
    incomeList.appendChild(row);
  });

  const paymentList = $('#paymentList');
  paymentList.innerHTML = state.payments.length ? '' : '<div class="empty">No payments sent yet</div>';
  state.payments.forEach(p => {
    const row = document.createElement('div');
    row.className = 'row-card row-pay';
    row.innerHTML = `
      <button class="row-delete" data-type="payment" data-id="${p.id}" title="Delete">✕</button>
      <div class="row-top"><span class="name">${escapeHtml(p.destinationName)}</span><span class="date">${fmtDate(p.date)}</span></div>
      <div class="amt">${fmtPKR(p.amountPKR)}</div>`;
    paymentList.appendChild(row);
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/* ---------- Delete handling ---------- */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.row-delete');
  if (!btn) return;
  const { type, id } = btn.dataset;
  if (!confirm('Delete this entry?')) return;
  const endpoint = type === 'income' ? 'income.php' : 'payments.php';
  await fetch(`${API}/${endpoint}?id=${id}`, { method: 'DELETE' });
  showToast('Deleted');
  loadAll();
});

/* ---------- Modals ---------- */
function openModal(id) {
  $(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  $(id).classList.remove('open');
  document.body.style.overflow = '';
}

let incomeCurrency = 'USD';

$('#currencyToggle').addEventListener('click', (e) => {
  const btn = e.target.closest('.ct-btn');
  if (!btn) return;
  incomeCurrency = btn.dataset.currency;
  document.querySelectorAll('.ct-btn').forEach(b => b.classList.toggle('active', b === btn));
  $('#incomeAmountPrefix').textContent = incomeCurrency;
  updateIncomePreview();
});

$('#fabIncome').addEventListener('click', () => {
  $('#incomeForm').reset();
  $('#incomeDate').value = todayISO();
  $('#incomePercent').value = 5;
  $('#incomeResult').hidden = true;
  incomeCurrency = 'USD';
  document.querySelectorAll('.ct-btn').forEach(b => b.classList.toggle('active', b.dataset.currency === 'USD'));
  $('#incomeAmountPrefix').textContent = 'USD';
  state.currentRate = null;
  updateIncomePreview();
  fetchRate();
  openModal('#incomeModal');
});
$('#incomeCancel').addEventListener('click', () => closeModal('#incomeModal'));
$('#incomeModal').addEventListener('click', (e) => { if (e.target.id === 'incomeModal') closeModal('#incomeModal'); });

$('#fabSend').addEventListener('click', () => {
  $('#paymentForm').reset();
  $('#paymentDate').value = todayISO();
  $('#paymentAvailable').textContent = fmtPKR(state.summary.availablePKR);
  $('#paymentWarn').classList.remove('show');
  updatePaymentValidity();
  openModal('#paymentModal');
});
$('#paymentCancel').addEventListener('click', () => closeModal('#paymentModal'));
$('#paymentModal').addEventListener('click', (e) => { if (e.target.id === 'paymentModal') closeModal('#paymentModal'); });

/* ---------- Exchange rate ---------- */
async function fetchRate() {
  const pill = $('#ratePill');
  pill.classList.remove('error');
  pill.innerHTML = '<span class="dot loading"></span> Fetching live exchange rate...';
  try {
    const res = await fetch(`${API}/rate.php`);
    if (!res.ok) throw new Error('rate fetch failed');
    const data = await res.json();
    state.currentRate = data.rate;
    pill.innerHTML = `<span class="dot"></span> 1 USD = ${data.rate.toFixed(2)} PKR (live)`;
    updateIncomePreview();
  } catch (e) {
    pill.classList.add('error');
    pill.innerHTML = '<span class="dot"></span> Could not fetch live rate <button id="retryRate">Retry</button>';
    $('#retryRate').addEventListener('click', fetchRate);
    updateIncomePreview();
  }
}

/* ---------- Add Income form ---------- */
function updateIncomePreview() {
  const amountInput = parseFloat($('#incomeAmount').value) || 0;
  const percent = parseFloat($('#incomePercent').value) || 0;
  const name = $('#incomeSource').value.trim();
  const canSave = amountInput > 0 && name !== '' && percent > 0 && state.currentRate;
  $('#incomeSave').disabled = !canSave;

  $('#previewPercentLabel').textContent = percent || 0;

  if (amountInput > 0 && state.currentRate && percent > 0) {
    const amountUSD = incomeCurrency === 'USD' ? amountInput : amountInput / state.currentRate;
    const amountPKR = incomeCurrency === 'USD' ? amountInput * state.currentRate : amountInput;
    const charityUSD = amountUSD * (percent / 100);
    const charityPKR = amountPKR * (percent / 100);

    $('#incomeResult').hidden = false;
    $('#previewBalanceUSD').textContent = fmtUSD(amountUSD);
    $('#previewBalancePKR').textContent = fmtPKR(amountPKR);
    $('#previewCharityUSD').textContent = fmtUSD(charityUSD);
    $('#previewCharityPKR').textContent = fmtPKR(charityPKR);
  } else {
    $('#incomeResult').hidden = true;
  }
}
$('#incomeAmount').addEventListener('input', updateIncomePreview);
$('#incomePercent').addEventListener('input', updateIncomePreview);
$('#incomeSource').addEventListener('input', updateIncomePreview);

$('#incomeQuickPercent').addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-pct]');
  if (!btn) return;
  $('#incomePercent').value = btn.dataset.pct;
  updateIncomePreview();
});

$('#incomeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const sourceName = $('#incomeSource').value.trim();
  const date = $('#incomeDate').value;
  const amountInput = parseFloat($('#incomeAmount').value);
  const percent = parseFloat($('#incomePercent').value);

  if (!sourceName || !date || !amountInput || !percent || !state.currentRate) return;

  const amountUSD = incomeCurrency === 'USD' ? amountInput : amountInput / state.currentRate;

  $('#incomeSave').disabled = true;
  try {
    const res = await fetch(`${API}/income.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sourceName, date, amountUSD, exchangeRate: state.currentRate, charityPercent: percent }),
    });
    if (!res.ok) throw new Error((await res.json()).error || 'Failed to save');
    closeModal('#incomeModal');
    showToast('Income added');
    loadAll();
  } catch (err) {
    alert(err.message);
    $('#incomeSave').disabled = false;
  }
});

/* ---------- Send Charity form ---------- */
function updatePaymentValidity() {
  const amount = parseFloat($('#paymentAmount').value) || 0;
  const name = $('#paymentDestination').value.trim();
  // Going negative is allowed on purpose (paying out of pocket ahead of the
  // balance), so this is just a heads-up — it never blocks Save.
  const exceeds = amount > state.summary.availablePKR + 0.01;
  $('#paymentWarn').classList.toggle('show', exceeds && amount > 0);
  $('#paymentSave').disabled = !(amount > 0 && name !== '');
}
$('#paymentAmount').addEventListener('input', updatePaymentValidity);
$('#paymentDestination').addEventListener('input', updatePaymentValidity);

$('#quickPercent').addEventListener('click', (e) => {
  const btn = e.target.closest('button[data-pct]');
  if (!btn) return;
  const pct = parseFloat(btn.dataset.pct);
  const amount = (state.summary.availablePKR * pct) / 100;
  $('#paymentAmount').value = amount.toFixed(2);
  updatePaymentValidity();
});

$('#paymentForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const destinationName = $('#paymentDestination').value.trim();
  const date = $('#paymentDate').value;
  const amountPKR = parseFloat($('#paymentAmount').value);

  if (!destinationName || !date || !amountPKR) return;

  $('#paymentSave').disabled = true;
  try {
    const res = await fetch(`${API}/payments.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ destinationName, date, amountPKR }),
    });
    if (!res.ok) throw new Error((await res.json()).error || 'Failed to save');
    closeModal('#paymentModal');
    showToast('Charity sent');
    loadAll();
  } catch (err) {
    alert(err.message);
    $('#paymentSave').disabled = false;
  }
});

/* ---------- Init ---------- */
loadAll();
setInterval(loadAll, 30000); // keep mobile/desktop in sync
