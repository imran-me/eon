<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyType extends Model
{
    protected $fillable = ['company_id', 'name', 'slug', 'model_class'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hasModel(): bool
    {
        return !empty($this->model_class);
    }

    public function getParties(?int $companyId = null): \Illuminate\Support\Collection
    {
        if (!$this->model_class || !class_exists($this->model_class)) {
            return collect();
        }

        $query = ($this->model_class)::select('id', 'name')->orderBy('name');

        if ($companyId) {
            $instance = new ($this->model_class)();
            if (in_array('company_id', $instance->getFillable())) {
                $query->where('company_id', $companyId);
            }
        }

        return $query->get();
    }
}
