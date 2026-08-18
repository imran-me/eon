<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
    <input type="text"
           id="name"
           name="name"
            value="{{ old('name', $airline?->name ?? '') }}"
           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
           placeholder="Enter airline name">
    @error('name')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mb-4 flex items-center gap-3">
    <input type="hidden" name="status" value="0">
    <input type="checkbox"
           id="status"
           name="status"
           value="1"
           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
           {{ old('status', isset($airline) ? $airline->status : true) ? 'checked' : '' }}>
    <label for="status" class="text-sm font-medium text-gray-700">Active</label>
    @error('status')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>