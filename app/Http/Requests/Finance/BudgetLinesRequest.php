<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class BudgetLinesRequest extends FormRequest
{
    public const string AMOUNT_MAX = '9999999999.99';

    private const int REMARKS_MAX = 255;

    private const int VERSION_REMARKS_MAX = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array'],
            'lines.*.account_id' => ['required', 'integer', 'distinct'],
            'lines.*.amount' => ['required', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
            'lines.*.remarks' => ['nullable', 'string', 'max:'.self::REMARKS_MAX],
            'remarks' => ['nullable', 'string', 'max:'.self::VERSION_REMARKS_MAX],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function amounts(): array
    {
        $amounts = [];
        foreach ($this->lineInputs() as $line) {
            $amounts[$line['account_id']] = $line['amount'];
        }

        return $amounts;
    }

    /**
     * @return array<int, array{amount: string, remarks: string|null}>
     */
    public function revisedLines(): array
    {
        $lines = [];
        foreach ($this->lineInputs() as $line) {
            $lines[$line['account_id']] = ['amount' => $line['amount'], 'remarks' => $line['remarks']];
        }

        return $lines;
    }

    public function remarks(): ?string
    {
        return $this->filled('remarks') ? $this->string('remarks')->trim()->toString() : null;
    }

    /**
     * @return list<array{account_id: int, amount: string, remarks: string|null}>
     */
    private function lineInputs(): array
    {
        $lines = [];
        foreach ((array) $this->input('lines', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $remarks = is_string($line['remarks'] ?? null) ? trim($line['remarks']) : '';
            $lines[] = [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'amount' => (string) ($line['amount'] ?? '0'),
                'remarks' => $remarks === '' ? null : $remarks,
            ];
        }

        return $lines;
    }
}
