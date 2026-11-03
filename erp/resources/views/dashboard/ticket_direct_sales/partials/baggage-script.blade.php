<script>
function baggageAmounts(card) {
  const cost = parseFloat(card.querySelector('.baggage-cost')?.value) || 0;
  const company = card.querySelector('.baggage-bearer')?.value === 'company';
  const input = card.querySelector('.baggage-charge');
  if (input) {
    if (company) input.value = '0';
    input.readOnly = company;
  }
  const charge = company ? 0 : (parseFloat(input?.value) || 0);
  const profit = charge - cost;
  const output = card.querySelector('.baggage-profit');
  if (output) {
    output.value = profit.toFixed(2);
    output.style.color = profit < 0 ? '#b91c1c' : '#15803d';
  }
  return { cost, charge, profit };
}
document.addEventListener('input', function (event) {
  if (!event.target.closest('.baggage-zone')) return;
  const card = event.target.closest('.ds-pax-card') || event.target.closest('form');
  if (!card) return;
  baggageAmounts(card);
  if (typeof profitOf === 'function') { profitOf(card); recalcSummary(); }
  if (typeof teRecalc === 'function') teRecalc();
});
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.baggage-zone').forEach(zone => baggageAmounts(zone.closest('.ds-pax-card') || zone.closest('form')));
});
</script>
