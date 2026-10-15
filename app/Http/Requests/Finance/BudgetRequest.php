<?php

namespace App\Http\Requests\Finance;

use App\Domain\Residents\NameNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    public const int YEAR_MIN = 2000;

    public const int YEAR_MAX = 2100;

    private const int TITLE_MAX = 100;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => NameNormalizer::normalize($this->input('title'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fiscal_year' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', 'between:'.self::YEAR_MIN.','.self::YEAR_MAX],
            'title' => [$this->isMethod('POST') ? 'nullable' : 'required', 'string', 'max:'.self::TITLE_MAX],
        ];
    }

    public function fiscalYear(): int
    {
        return $this->integer('fiscal_year');
    }

    public function title(): string
    {
        return $this->filled('title')
            ? $this->string('title')->toString()
            : (string) __('finance.budgets.default_title', ['year' => $this->fiscalYear()]);
    }
}
