<?php

namespace App\Services;

use App\Models\User;
use App\Models\VendorCategory;
use App\Models\VendorProfile;
use App\Models\VendorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class VendorClassificationService
{
    public const PROJECT_COMPANIES = [3, 6];

    public static function installed(): bool
    {
        return Schema::hasTable('vendor_types') && Schema::hasTable('vendor_categories')
            && Schema::hasTable('vendor_profiles') && Schema::hasTable('user_vendor_category');
    }

    public static function formData(?User $vendor = null): array
    {
        if (! self::installed()) {
            return ['vendorTypes' => collect(), 'vendorCategories' => collect(), 'selectedVendorType' => null, 'selectedVendorCategories' => []];
        }

        $vendorTypes = VendorType::where('status', true)->orderBy('sort_order')->orderBy('name')->get();
        $vendorCategories = VendorCategory::where('status', true)->orderBy('sort_order')->orderBy('name')->get();
        $requestedType = ! $vendor ? trim((string) request()->query('vendor_type')) : '';
        $selectedVendorType = $vendor?->vendorProfile?->vendor_type_id
            ?: ($requestedType !== '' ? $vendorTypes->firstWhere('slug', $requestedType)?->id : null);
        $selectedVendorCategories = $vendor?->vendorCategories->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        return compact('vendorTypes', 'vendorCategories', 'selectedVendorType', 'selectedVendorCategories');
    }

    public static function rules(Request $request): array
    {
        if (! self::installed()) return [];

        $required = collect($request->input('company_ids', []))->map(fn ($id) => (int) $id)
            ->intersect(self::PROJECT_COMPANIES)->isNotEmpty();

        return [
            'vendor_type_id' => [$required ? 'required' : 'nullable', 'integer', Rule::exists('vendor_types', 'id')->where('status', true)],
            'vendor_category_ids' => [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : 'min:0'],
            'vendor_category_ids.*' => ['integer', 'distinct', Rule::exists('vendor_categories', 'id')->where('status', true)],
        ];
    }

    public static function sync(User $vendor, ?int $typeId, array $categoryIds = []): void
    {
        if (! self::installed()) return;

        if (! $typeId) {
            DB::table('user_vendor_category')->where('user_id', $vendor->id)->delete();
            DB::table('vendor_profiles')->where('user_id', $vendor->id)->delete();
            return;
        }

        $categoryIds = collect($categoryIds)->map(fn ($id) => (int) $id)->unique()->values();
        if (VendorCategory::whereIn('id', $categoryIds)->where('vendor_type_id', '!=', $typeId)->exists()) {
            throw ValidationException::withMessages(['vendor_category_ids' => 'Every category must belong to the selected vendor type.']);
        }

        VendorProfile::updateOrCreate(['user_id' => $vendor->id], ['vendor_type_id' => $typeId]);
        $vendor->vendorCategories()->sync($categoryIds->all());
    }
}
