<div>
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Custom Time <span class="text-red-500">*</span></label>
    <input type="hidden" id="{{ $prefix }}_transaction_time" name="transaction_time">
    <div class="grid gap-1.5 items-center" style="grid-template-columns:minmax(72px,1fr) auto minmax(72px,1fr) minmax(82px,1.1fr);">
        <select id="{{ $prefix }}_time_hour" class="form-select w-full px-2 py-2 border border-gray-300 rounded-lg text-sm" required aria-label="Hour">
            <option value="">hh</option>
            @for($hour = 1; $hour <= 12; $hour++)<option value="{{ $hour }}">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</option>@endfor
        </select>
        <span class="font-bold text-gray-400">:</span>
        <select id="{{ $prefix }}_time_minute" class="form-select w-full px-2 py-2 border border-gray-300 rounded-lg text-sm" required aria-label="Minute">
            <option value="">mm</option>
            @for($minute = 0; $minute < 60; $minute++)<option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>@endfor
        </select>
        <select id="{{ $prefix }}_time_ampm" class="form-select w-full px-2 py-2 border border-gray-300 rounded-lg text-sm" required aria-label="AM or PM">
            <option value="AM">AM</option><option value="PM">PM</option>
        </select>
    </div>
    <p id="{{ $prefix }}_transaction_time_error" class="text-red-500 text-xs mt-1 hidden error-message">Please select the transaction time</p>
</div>
