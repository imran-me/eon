{{-- A file's journey, drawn as the flight passenger's journey card
     (contract-flights/partials/passenger-journey-row): the stub (progress
     ring · now · next · flag) and the track of pins. CSS is the flight's own
     partials/journey-card-styles; the pins are buttons that journey-stage-script
     saves through the FILE stage endpoint ([data-file-stage]).
     Expects $file, $statuses, $colspan; $openStrip shows it on load (group
     ledger), otherwise it waits for the Status button / a row click.
     `data-sort-attach` keeps it under its file when a column is sorted. --}}
@php
 $mjKeys = array_keys($statuses);
 $mjCurrent = array_search($file->status, $mjKeys, true);
 $mjCurrent = $mjCurrent === false ? 0 : $mjCurrent;
 $mjLast = max(1, count($mjKeys) - 1);
 $mjPct = (int) round($mjCurrent / $mjLast * 100);
 $mjNext = array_values($statuses)[$mjCurrent + 1] ?? null;
 $mjFlag = $file->exception_flag ? (\App\Models\ContractFile::EXCEPTION_FLAGS[$file->exception_flag] ?? $file->exception_flag) : null;
 $mjCanEdit = (bool) auth()->user()?->can('edit contract file');
 $mjIcons = [
  'file_received' => 'fa-folder-plus', 'information_entered' => 'fa-keyboard', 'documents_pending' => 'fa-hourglass-half',
  'documents_verified' => 'fa-file-circle-check', 'application_prepared' => 'fa-file-pen', 'submitted_to_vendor' => 'fa-paper-plane',
  'biometrics_appointment' => 'fa-fingerprint', 'under_process' => 'fa-gears', 'additional_documents' => 'fa-file-circle-plus',
  'decision_received' => 'fa-envelope-open-text', 'approved' => 'fa-circle-check', 'passport_ready' => 'fa-passport',
  'delivered' => 'fa-box-archive', 'case_closed' => 'fa-flag-checkered',
 ];
@endphp
<tr class="mfp-journey-row" data-sort-attach data-cfp-journey @unless($openStrip ?? false) hidden @endunless @if($ringColor ?? null) style="--mj-border:{{ $ringColor }}" @endif>
 <td colspan="{{ $colspan ?? 9 }}">
  <div class="mj-card">
   <div class="mj-stub">
    <span class="mj-ring" style="--mj-p:{{ $mjPct }}%" title="{{ $mjPct }}% of the journey done"><span>{{ $mjPct }}%</span></span>
    <span class="mj-now">
     <small><i class="fas fa-route"></i> Now</small>
     <b>{{ $file->status_label }}</b>
     @if($mjNext)<span class="mj-next">Next <i class="fas fa-arrow-right"></i> {{ $mjNext }}</span>@else<span class="mj-next">Journey complete</span>@endif
     @if($mjFlag)<span class="mj-flag"><i class="fas fa-triangle-exclamation"></i>{{ $mjFlag }}</span>@endif
    </span>
   </div>
   <div class="mj-scroll"><ol class="mj-route" style="--mj-progress:{{ ($mjCurrent / $mjLast) * 100 }}%" aria-label="{{ $file->file_number }} journey">
    @foreach($statuses as $key => $label)
     @php
      $i = $loop->index;
      $state = $i < $mjCurrent ? 'is-done' : ($i === $mjCurrent ? 'is-current' : 'is-next');
      $stateLabel = $i < $mjCurrent ? 'Completed' : ($i === $mjCurrent ? 'Current stage' : 'Upcoming');
      $focus = abs($i - $mjCurrent) <= 1 ? 'is-focus' : 'is-compact';
     @endphp
     <li class="mj-step {{ $state }} {{ $focus }}" aria-current="{{ $i === $mjCurrent ? 'step' : 'false' }}">
      <button type="button" class="mj-stage" data-tip="{{ $label }}&#10;{{ $stateLabel }}{!! $mjCanEdit && $i !== $mjCurrent ? '&#10;Click to set this stage' : '' !!}" data-file-stage="{{ $key }}" data-file-id="{{ $file->id }}" data-label="{{ $label }}" data-name="{{ $file->applicant_name }}" data-from="{{ $file->status_label }}" data-current="{{ $i === $mjCurrent ? 1 : 0 }}" @disabled(! $mjCanEdit) aria-label="{{ $label }}: {{ $stateLabel }}">
       <span class="mj-pin"><i class="fas {{ $state === 'is-done' ? 'fa-check' : ($mjIcons[$key] ?? 'fa-circle') }}" aria-hidden="true"></i></span>
       <span class="mj-copy"><b>{{ $label }}</b><small>{{ $stateLabel }}</small></span>
      </button>
     </li>
    @endforeach
   </ol></div>
  </div>
 </td>
</tr>
@include('contract-files.journey-stage-script')
