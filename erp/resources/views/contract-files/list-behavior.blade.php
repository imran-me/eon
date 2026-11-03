<script>
(() => {
 const list = document.getElementById('cfp-list');
 if (!list) return;
 // Status: every file's journey strip, hidden on every load — as Flights & Passengers.
 const button = document.getElementById('cfp-status');
 const strips = () => list.querySelectorAll('tr[data-cfp-journey]');
 const paint = on => {
  strips().forEach(row => { row.hidden = !on; row.previousElementSibling?.setAttribute('aria-expanded', String(on)); });
  button?.classList.toggle('is-on', on);
  button?.setAttribute('aria-pressed', String(on));
  if (button) button.title = on ? 'Hide each file’s status tracker' : 'Show each file’s status tracker under its row';
 };
 const syncStatus = () => {
  const on = Array.from(strips()).some(row => !row.hidden);
  button?.classList.toggle('is-on', on);
  button?.setAttribute('aria-pressed', String(on));
 };
 list.querySelectorAll('[data-file-row]').forEach(row => {
  const journey = row.nextElementSibling;
  if (!journey?.matches('[data-cfp-journey]')) return;
  row.tabIndex = 0; row.setAttribute('aria-expanded', String(!journey.hidden));
  const toggle = () => { journey.hidden = !journey.hidden; row.setAttribute('aria-expanded', String(!journey.hidden)); syncStatus(); };
  row.addEventListener('click', event => { if (event.target.closest('a,button,input,select,textarea,label,summary,details,[onclick],[role=button]') || String(window.getSelection?.() || '').trim()) return; toggle(); });
  row.addEventListener('keydown', event => { if (event.target !== row || !['Enter', ' '].includes(event.key)) return; event.preventDefault(); toggle(); });
 });
 syncStatus();
 button?.addEventListener('click', () => paint(button.getAttribute('aria-pressed') !== 'true'));
 const filter = document.getElementById('cfp-filter');
 if (!filter) return;
 const today = @json(now()->toDateString());
 filter.querySelectorAll('[data-cfp-date]').forEach(preset => preset.addEventListener('click', () => {
  const key = preset.dataset.cfpDate;
  if (key === 'clear') { filter.elements.from_date.value = ''; filter.elements.to_date.value = ''; filter.requestSubmit(); return; }
  const from = new Date(today + 'T12:00:00'), to = new Date(today + 'T12:00:00');
  if (key === 'yesterday') { from.setDate(from.getDate() - 1); to.setDate(to.getDate() - 1); }
  if (key === 'tomorrow') { from.setDate(from.getDate() + 1); to.setDate(to.getDate() + 1); }
  if (key === 'week') { from.setDate(from.getDate() - ((from.getDay() + 6) % 7)); to.setTime(from.getTime()); to.setDate(to.getDate() + 6); }
  const ymd = d => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
  filter.elements.from_date.value = ymd(from); filter.elements.to_date.value = ymd(to);
  filter.querySelectorAll('[data-cfp-date]').forEach(item => item.classList.toggle('is-active', item === preset));
  filter.requestSubmit();
 }));
})();
</script>
