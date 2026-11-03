<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\ContractFile;
use App\Models\ContractFileCategory;
use App\Models\ContractFilePricePreset;
use App\Models\ContractFileRequirement;
use App\Models\ContractFileVisaType;
use App\Models\Country;
use App\Services\ContractFileSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contract File → Setup (owner, 2026-10-25), modelled on Contract Flight's Flight Category tab:
 * Requirements (the requirement builder), Price Presets and Visa Types, beside the existing Job
 * Categories. {kind} picks the page; match() is what the file entry calls when a country, visa type
 * or job category is chosen. Same permissions as Job Categories, as Flight Category's sub-pages share
 * Flight Category's.
 */
class ContractFileSetupController implements HasMiddleware
{
    public const KINDS = ['requirements', 'price-presets', 'visa-types'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view contract file category|view all contract file category', only: ['index']),
            new Middleware('permission:create contract file category', only: ['store']),
            new Middleware('permission:edit contract file category', only: ['update']),
            new Middleware('permission:delete contract file category', only: ['destroy']),
            new Middleware('permission:view contract file|view all contract file|create contract file|edit contract file', only: ['match']),
        ];
    }

    public function index(Request $request, string $role, string $kind)
    {
        $installed = ContractFileSetup::installed();
        $countries = Country::orderBy('name')->get(['id', 'name', 'code']);
        $categories = ContractFileCategory::with('country')->where('status', 'active')->orderBy('name')->get();
        $visaTypes = $installed ? ContractFileVisaType::orderBy('name')->get() : collect();
        $rows = collect();
        $stats = [];
        $search = trim((string) $request->input('search'));

        if ($installed) {
            if ($kind === 'visa-types') {
                $rows = ContractFileVisaType::withCount([
                    'files',
                ])->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
                    ->orderBy('name')->get();
                $ruleCounts = ContractFileRequirement::selectRaw('contract_file_visa_type_id, count(*) n')->whereNotNull('contract_file_visa_type_id')->groupBy('contract_file_visa_type_id')->pluck('n', 'contract_file_visa_type_id');
                $presetCounts = ContractFilePricePreset::selectRaw('contract_file_visa_type_id, count(*) n')->whereNotNull('contract_file_visa_type_id')->groupBy('contract_file_visa_type_id')->pluck('n', 'contract_file_visa_type_id');
                $rows->each(function ($type) use ($ruleCounts, $presetCounts) {
                    $type->rules_count = (int) ($ruleCounts[$type->id] ?? 0);
                    $type->presets_count = (int) ($presetCounts[$type->id] ?? 0);
                });
                $stats = [
                    'total' => $rows->where('status', 'active')->count(),
                    'files' => $rows->sum('files_count'),
                    'rules' => $rows->sum('rules_count'),
                    'presets' => $rows->sum('presets_count'),
                ];
            } else {
                $model = $kind === 'requirements' ? ContractFileRequirement::class : ContractFilePricePreset::class;
                $rows = $model::with(['country', 'visaType', 'category'])
                    ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', $request->integer('country_id')))
                    ->when($request->filled('visa_type_id'), fn ($q) => $q->where('contract_file_visa_type_id', $request->integer('visa_type_id')))
                    ->get()
                    ->when($search !== '', fn ($c) => $c->filter(fn ($rule) => str_contains(mb_strtolower(ContractFileSetup::label($rule).' '.($rule->documents ?? '').' '.($rule->notes ?? '')), mb_strtolower($search))))
                    ->sortBy([fn ($a, $b) => ContractFileSetup::weight($a) <=> ContractFileSetup::weight($b), fn ($a, $b) => strcmp(ContractFileSetup::label($a), ContractFileSetup::label($b))])
                    ->values();
                $active = $rows->where('status', 'active');
                $stats = $kind === 'requirements' ? [
                    'total' => $active->count(),
                    'countries' => $active->pluck('country_id')->filter()->unique()->count(),
                    'visa_types' => $active->pluck('contract_file_visa_type_id')->filter()->unique()->count(),
                    'documents' => $active->flatMap(fn ($r) => $r->documents_list)->map(fn ($d) => mb_strtolower($d))->unique()->count(),
                ] : [
                    'total' => $active->count(),
                    'with_cost' => $active->whereNotNull('processing_cost')->count(),
                    'with_training' => $active->whereNotNull('training_fee')->count(),
                    'with_days' => $active->whereNotNull('expected_days')->count(),
                ];
            }
        }

        return view('contract-file-setup.index', compact('kind', 'installed', 'countries', 'categories', 'visaTypes', 'rows', 'stats', 'role'));
    }

    public function store(Request $request, string $role, string $kind): JsonResponse
    {
        return $this->save($request, $kind, null);
    }

    public function update(Request $request, string $role, string $kind, int $id): JsonResponse
    {
        return $this->save($request, $kind, $id);
    }

    public function destroy(string $role, string $kind, int $id): JsonResponse
    {
        abort_unless(ContractFileSetup::installed(), 503, 'Contract File Setup is not installed yet.');
        $record = $this->model($kind)::find($id);
        if (! $record) {
            return response()->json(['success' => false, 'message' => 'Not found.']);
        }
        if ($kind === 'visa-types' && ContractFile::where('contract_file_visa_type_id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Files use this visa type — set it Inactive instead of deleting it.']);
        }
        $record->delete();

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    /** What the file entry fills for a country / visa type / job category. */
    public function match(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => 'nullable|integer',
            'visa_type_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
        ]);

        return response()->json(['success' => true] + ContractFileSetup::resolve(
            isset($data['country_id']) ? (int) $data['country_id'] : null,
            isset($data['visa_type_id']) ? (int) $data['visa_type_id'] : null,
            isset($data['category_id']) ? (int) $data['category_id'] : null,
        ));
    }

    private function save(Request $request, string $kind, ?int $id): JsonResponse
    {
        abort_unless(ContractFileSetup::installed(), 503, 'Contract File Setup is not installed yet. Run its migration first.');
        $model = $this->model($kind);
        $record = $id ? $model::find($id) : new $model;
        if (! $record) {
            return response()->json(['success' => false, 'message' => 'Not found.']);
        }

        try {
            $values = $kind === 'visa-types' ? $this->visaTypeValues($request, $id) : $this->ruleValues($request, $kind, $id);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        if (! $id) {
            $values['created_by'] = Auth::id();
        }
        $record->fill($values)->save();

        return response()->json(['success' => true, 'message' => $id ? 'Saved.' : 'Added.', 'id' => $record->id]);
    }

    private function visaTypeValues(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contract_file_visa_types', 'name')->whereNull('deleted_at')->ignore($id)],
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:active,inactive',
        ]);
        if (! $id) {
            $data['code'] = ContractFileVisaType::makeCode($data['name']);
        }

        return $data;
    }

    private function ruleValues(Request $request, string $kind, ?int $id): array
    {
        $rules = [
            'country_id' => 'nullable|integer|exists:countries,id',
            'contract_file_visa_type_id' => ['nullable', 'integer', Rule::exists('contract_file_visa_types', 'id')->whereNull('deleted_at')],
            'contract_file_category_id' => ['nullable', 'integer', Rule::exists('contract_file_categories', 'id')->whereNull('deleted_at')],
            'status' => 'required|in:active,inactive',
        ];
        $rules += $kind === 'requirements' ? [
            'documents' => 'required|string|max:5000',
            'notes' => 'nullable|string|max:2000',
        ] : [
            'processing_cost' => 'nullable|numeric|min:0|max:999999999',
            'training_fee' => 'nullable|numeric|min:0|max:999999999',
            'expected_days' => 'nullable|integer|min:0|max:3650',
        ];
        $data = $request->validate($rules);
        $scope = array_intersect_key($data, ContractFileSetup::WEIGHTS);
        $scope += array_fill_keys(array_keys(ContractFileSetup::WEIGHTS), null);

        if (! array_filter($scope)) {
            throw ValidationException::withMessages(['country_id' => 'Choose at least one of country, visa type or job category.']);
        }
        if ($scope['contract_file_category_id'] && $scope['country_id']) {
            $categoryCountry = ContractFileCategory::whereKey($scope['contract_file_category_id'])->value('country_id');
            if ($categoryCountry && (int) $categoryCountry !== (int) $scope['country_id']) {
                throw ValidationException::withMessages(['contract_file_category_id' => 'That job category belongs to another country.']);
            }
        }
        if ($kind === 'requirements') {
            $list = (new ContractFileRequirement(['documents' => $data['documents']]))->documents_list;
            if (! $list) {
                throw ValidationException::withMessages(['documents' => 'Add at least one document.']);
            }
            $data['documents'] = implode("\n", $list);
        } elseif ($data['processing_cost'] === null && $data['training_fee'] === null && $data['expected_days'] === null) {
            throw ValidationException::withMessages(['processing_cost' => 'Enter at least one figure.']);
        }

        // One rule per scope, so it is always clear which one a file takes.
        $twin = $this->model($kind)::query();
        foreach ($scope as $part => $value) {
            $value === null ? $twin->whereNull($part) : $twin->where($part, $value);
        }
        if ($twin->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages(['country_id' => 'A '.($kind === 'requirements' ? 'requirement' : 'price preset').' for exactly this scope already exists — edit that one.']);
        }

        return array_merge($data, $scope);
    }

    /** @return class-string<Model> */
    private function model(string $kind): string
    {
        return match ($kind) {
            'requirements' => ContractFileRequirement::class,
            'price-presets' => ContractFilePricePreset::class,
            'visa-types' => ContractFileVisaType::class,
            default => abort(404),
        };
    }
}
