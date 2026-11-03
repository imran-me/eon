<?php

namespace App\Services;

use App\Models\ContractFileCategory;
use App\Models\ContractFilePricePreset;
use App\Models\ContractFileRequirement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Contract File → Setup, resolved for one file (owner, 2026-10-25: "while entering the files, if we
 * select any visa types or country the requirements add up automatically, also cost prices").
 *
 * A rule (requirement or price preset) has a scope of up to three parts — country, visa type, job
 * category — and an empty part means "any". A rule MATCHES a file when every part it names is the
 * file's. Specificity is the weight of the parts it names: category 4, visa type 2, country 1, so
 * every combination ranks differently (country 1 < visa 2 < country+visa 3 < category 4 < … < all 7).
 *
 *   documents  every matching requirement adds its list, general first, then more specific; the job
 *              category's own list leads. Repeats (any case) are kept once.
 *   figures    processing cost, training fee, expected days — each from the most specific matching
 *              preset that HAS that figure. Processing cost falls back to the category's visa rate,
 *              as the builder did before Setup existed. The client price is never preset (owner).
 *
 * The builder, the edit modal and the server's save all go through resolve(), so the checklist on
 * screen and the one stored can never differ. Before the migration runs, installed() is false and
 * resolve() returns exactly what the category alone gave — the file entry works as it did.
 */
class ContractFileSetup
{
    public const WEIGHTS = ['contract_file_category_id' => 4, 'contract_file_visa_type_id' => 2, 'country_id' => 1];

    public const FIGURES = ['processing_cost', 'training_fee', 'expected_days'];

    private static ?bool $installed = null;

    public static function installed(): bool
    {
        if (self::$installed !== null) {
            return self::$installed;
        }
        try {
            return self::$installed = Schema::hasTable('contract_file_visa_types')
                && Schema::hasTable('contract_file_requirements')
                && Schema::hasTable('contract_file_price_presets')
                && Schema::hasColumn('contract_files', 'contract_file_visa_type_id');
        } catch (\Throwable) {
            return self::$installed = false;
        }
    }

    /** @return array{documents: list<string>, sources: list<array{label: string, documents: list<string>}>, figures: array<string, float|int|null>, figure_sources: array<string, string|null>} */
    public static function resolve(?int $countryId, ?int $visaTypeId, ?int $categoryId): array
    {
        $category = $categoryId ? ContractFileCategory::with('country')->find($categoryId) : null;
        $documents = [];
        $sources = [];
        $push = function (string $label, array $list) use (&$documents, &$sources) {
            $added = [];
            foreach ($list as $name) {
                $key = mb_strtolower(trim($name));
                if ($key === '' || isset($documents[$key])) {
                    continue;
                }
                $documents[$key] = trim($name);
                $added[] = trim($name);
            }
            if ($list) {
                $sources[] = ['label' => $label, 'documents' => $added];
            }
        };
        if ($category && $category->documents_list) {
            $push('Job category · '.$category->name, $category->documents_list);
        }

        $figures = array_fill_keys(self::FIGURES, null);
        $figureSources = array_fill_keys(self::FIGURES, null);

        if (self::installed()) {
            $scope = ['country_id' => $countryId, 'contract_file_visa_type_id' => $visaTypeId, 'contract_file_category_id' => $categoryId];
            foreach (self::matching(ContractFileRequirement::with(['country', 'visaType', 'category'])->where('status', 'active')->get(), $scope)->sortBy(fn ($rule) => self::weight($rule)) as $rule) {
                $push(self::label($rule), $rule->documents_list);
            }
            $presets = self::matching(ContractFilePricePreset::with(['country', 'visaType', 'category'])->where('status', 'active')->get(), $scope)
                ->sortByDesc(fn ($rule) => self::weight($rule));
            foreach (self::FIGURES as $figure) {
                $preset = $presets->first(fn ($rule) => $rule->{$figure} !== null);
                if ($preset) {
                    $figures[$figure] = $preset->{$figure};
                    $figureSources[$figure] = self::label($preset);
                }
            }
        }

        if ($figures['processing_cost'] === null && $category && (float) $category->visa_rate > 0) {
            $figures['processing_cost'] = (float) $category->visa_rate;
            $figureSources['processing_cost'] = 'Job category · '.$category->name;
        }

        return ['documents' => array_values($documents), 'sources' => $sources, 'figures' => $figures, 'figure_sources' => $figureSources];
    }

    /** Rules whose every named part is the file's. */
    public static function matching(Collection $rules, array $scope): Collection
    {
        return $rules->filter(function ($rule) use ($scope) {
            foreach (array_keys(self::WEIGHTS) as $part) {
                if ($rule->{$part} !== null && (int) $rule->{$part} !== (int) ($scope[$part] ?? 0)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    public static function weight($rule): int
    {
        $weight = 0;
        foreach (self::WEIGHTS as $part => $points) {
            if ($rule->{$part} !== null) {
                $weight += $points;
            }
        }

        return $weight;
    }

    /** "Fiji Islands · Work Permit · GARMENTS"; "Any country" when the rule names nothing. */
    public static function label($rule): string
    {
        $parts = array_filter([$rule->country?->name, $rule->visaType?->name, $rule->category?->name]);

        return $parts ? implode(' · ', $parts) : 'Any file';
    }
}
