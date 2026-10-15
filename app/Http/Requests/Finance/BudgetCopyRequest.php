<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class BudgetCopyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['source_budget_id' => ['required', 'integer']];
    }

    public function sourceBudgetId(): int
    {
        return $this->integer('source_budget_id');
    }
}
