<?php

namespace App\Http\Requests\Documents;

use App\Domain\Residents\NameNormalizer;
use App\Models\IssuedDocument;
use App\Rules\AvailableOrNumber;

trait ValidatesDocumentPayment
{
    public const string AMOUNT_MAX = '9999999999.99';

    private const int REFERENCE_MAX = 32;

    private const int PLACE_MAX = 255;

    private const array PAYMENT_TEXT_FIELDS = ['ctc_number', 'ctc_place', 'or_number'];

    protected function normalizePaymentText(): void
    {
        $normalized = [];
        foreach (self::PAYMENT_TEXT_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = NameNormalizer::normalize($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentRules(?IssuedDocument $document, bool $withDocStamp): array
    {
        return [
            'ctc_number' => ['nullable', 'string', 'max:'.self::REFERENCE_MAX],
            'ctc_place' => ['nullable', 'string', 'max:'.self::PLACE_MAX],
            'ctc_date' => ['nullable', 'date', 'before_or_equal:today'],
            'or_number' => ['nullable', 'string', 'max:'.self::REFERENCE_MAX, new AvailableOrNumber($document)],
            'amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
            ...($withDocStamp ? ['doc_stamp' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX]] : []),
        ];
    }

    /**
     * @return array{ctc_number: string|null, ctc_place: string|null, ctc_date: string|null, or_number: string|null, amount: string}
     */
    protected function paymentDetails(): array
    {
        return [
            'ctc_number' => $this->optionalText('ctc_number'),
            'ctc_place' => $this->optionalText('ctc_place'),
            'ctc_date' => $this->optionalText('ctc_date'),
            'or_number' => $this->optionalText('or_number'),
            'amount' => $this->optionalText('amount') ?? '0',
        ];
    }

    protected function optionalText(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->toString() : null;
    }
}
