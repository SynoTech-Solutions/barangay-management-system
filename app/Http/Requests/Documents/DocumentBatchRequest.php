<?php

namespace App\Http\Requests\Documents;

use App\Models\IssuedDocument;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class DocumentBatchRequest extends FormRequest
{
    public const int MAX_DOCUMENTS = 200;

    public const string IDS_FIELD = 'ids';

    /**
     * @return class-string<IssuedDocument>
     */
    abstract protected function model(): string;

    protected function idsField(): string
    {
        return self::IDS_FIELD;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return $this->batchRules($currentBarangay);
    }

    /**
     * @return array<string, mixed>
     */
    protected function batchRules(CurrentBarangay $currentBarangay): array
    {
        $field = $this->idsField();
        $table = (new ($this->model()))->getTable();

        return [
            $field => ['required', 'array', 'min:1', 'max:'.self::MAX_DOCUMENTS],
            $field.'.*' => ['integer', 'distinct', Rule::exists($table, 'id')->where('barangay_id', $currentBarangay->require()->id)],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->input($this->idsField(), [])));
    }
}
