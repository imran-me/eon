@php
    $flightEditTitle = $flightEditTitle ?? 'Edit Contract Flight';
    $flightEditSub = $flightEditSub ?? 'Update the route, agent and passengers';
@endphp

<div id="editModal" class="fcm-overlay hidden modal-backdrop">
    <div class="fcm-box">
        <div class="fcm-head" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
            <div class="fcm-head-ic"><i class="fas fa-pencil-alt"></i></div>
            <div>
                <div class="fcm-title">{{ $flightEditTitle }}</div>
                <div class="fcm-sub">{{ $flightEditSub }}</div>
            </div>
            <button type="button" class="fcm-close modal-close-edit">✕</button>
        </div>
        @include('contract-flights.flight-modal-steps', ['prefix' => 'edit'])
        <div class="fcm-body">
            <form id="editeForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="editItemId" name="id">

                {{-- ── Step 1: the flight itself ── --}}
                <div class="fm-pane active" data-pane="edit-flight">

                    @include("contract-flights.flight-basics", ["prefix" => "edit"])

                </div>{{-- /Step 1 --}}

                {{-- ── Step 2: the people, their tickets, services and money ── --}}
                <div class="fm-pane" data-pane="edit-passengers">
                    @include('contract-flights.passenger-builder', ['prefix' => 'edit'])
                </div>

                {{-- ── Step 3: read it back, then confirm ── --}}
                <div class="fm-pane" data-pane="edit-review">
                    @include('contract-flights.flight-review', ['prefix' => 'edit'])
                </div>
            </form>
        </div>
        <div class="fcm-foot">
            <span class="fcm-foot-spacer" id="edit_foot_hint"></span>
            <button type="button" class="fcm-btn fcm-btn-ghost modal-close-edit">✕ Cancel</button>
            <button type="button" class="fcm-btn fcm-btn-step fm-back" data-prefix="edit" style="display:none;"><i class="fas fa-arrow-left"></i> Back</button>
            <button type="button" class="fcm-btn fcm-btn-step fm-next" data-prefix="edit">Next <i class="fas fa-arrow-right"></i></button>
            <button id="editSubmit" type="button" class="fcm-btn" style="display:none;background:#7c3aed;color:#fff;"><i class="fas fa-save"></i> Update Flight</button>
        </div>
    </div>
</div>
