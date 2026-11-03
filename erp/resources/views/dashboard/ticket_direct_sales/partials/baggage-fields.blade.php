@if(\App\Services\TicketBaggage::ready())
  @php
    $bagPrefix = $prefix ?? '';
    $bagName = fn ($key) => $bagPrefix ? $bagPrefix.'['.$key.']' : $key;
    $bagValue = fn ($key) => isset($purchase) ? $purchase->getAttribute($key) : null;
    $bagEdit = ! $bagPrefix;
    $bagInput = $bagEdit ? 'te-in' : 'ds-field-input';
    $bagLabel = $bagEdit ? 'te-lbl' : 'ds-field-lbl';
  @endphp
  <div class="{{ $bagEdit ? 'te-zone' : 'ds-zone' }} baggage-zone">
    <div class="{{ $bagEdit ? 'te-zone-h' : 'ds-zone-h' }}"><i class="fas fa-suitcase"></i> Extra baggage</div>
    <div class="{{ $bagEdit ? 'te-row' : 'ds-row cols-6' }}" @if($bagEdit) style="--c:6" @endif>
      <div>
        <label class="{{ $bagLabel }}">Extra KG</label>
        <input type="number" step="0.01" min="0" name="{{ $bagName('extra_baggage_kg') }}" value="{{ $bagValue('extra_baggage_kg') }}" class="{{ $bagInput }} baggage-kg">
      </div>
      <div>
        <label class="{{ $bagLabel }}">Cost</label>
        <input type="number" step="0.01" min="0" name="{{ $bagName('extra_baggage_cost') }}" value="{{ $bagValue('extra_baggage_cost') }}" class="{{ $bagInput }} baggage-cost" style="text-align:right;color:#b91c1c">
      </div>
      <div>
        <label class="{{ $bagLabel }}">Agent charge</label>
        <input type="number" step="0.01" min="0" name="{{ $bagName('extra_baggage_agent_charge') }}" value="{{ $bagValue('extra_baggage_agent_charge') }}" class="{{ $bagInput }} baggage-charge" style="text-align:right;color:#b91c1c">
      </div>
      <div>
        <label class="{{ $bagLabel }}">Who pays</label>
        <select name="{{ $bagName('extra_baggage_bearer') }}" class="{{ $bagInput }} baggage-bearer" style="box-sizing:border-box;min-height:0">
          <option value="">Choose</option>
          <option value="customer" @selected($bagValue('extra_baggage_bearer') === 'customer')>Agent pays</option>
          <option value="company" @selected($bagValue('extra_baggage_bearer') === 'company')>We bear it</option>
        </select>
      </div>
      <div class="{{ $bagEdit ? 's2' : 'span-2' }}">
        <label class="{{ $bagLabel }}">Baggage profit</label>
        <input type="text" readonly tabindex="-1" class="{{ $bagInput }} baggage-profit" style="text-align:right;background:#f8fafc">
      </div>
    </div>
  </div>
@endif
