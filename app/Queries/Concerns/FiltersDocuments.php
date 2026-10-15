<?php

namespace App\Queries\Concerns;

use App\Models\IssuedDocument;
use App\Queries\Filters\DocumentListFilters;
use Illuminate\Database\Eloquent\Builder;

trait FiltersDocuments
{
    private const string ZERO = '0.00';

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  Builder<TDocument>  $query
     * @return Builder<TDocument>
     */
    protected function filterDocuments(Builder $query, DocumentListFilters $filters): Builder
    {
        return $query
            ->with(IssuedDocument::LIFECYCLE_RELATIONS)
            ->when($filters->status !== null, fn (Builder $inner) => $inner->where('status', $filters->status))
            ->when($filters->from !== null, fn (Builder $inner) => $inner->where('issued_at', '>=', $filters->from))
            ->when($filters->to !== null, fn (Builder $inner) => $inner->where('issued_at', '<=', $filters->to))
            ->orderByDesc('issued_at')
            ->orderByDesc('id');
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  Builder<TDocument>  $query
     * @return array{amount: string, doc_stamp: string, total: string}
     */
    protected function paymentTotals(Builder $query): array
    {
        $row = $query->notCancelled()->reorder()->toBase()->selectRaw(
            'coalesce(sum(amount), 0)::numeric(14, 2)::text as amount, coalesce(sum(doc_stamp), 0)::numeric(14, 2)::text as doc_stamp, coalesce(sum(amount + doc_stamp), 0)::numeric(14, 2)::text as total',
        )->first();

        return [
            'amount' => is_object($row) && is_string($row->amount ?? null) ? $row->amount : self::ZERO,
            'doc_stamp' => is_object($row) && is_string($row->doc_stamp ?? null) ? $row->doc_stamp : self::ZERO,
            'total' => is_object($row) && is_string($row->total ?? null) ? $row->total : self::ZERO,
        ];
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  Builder<TDocument>  $query
     */
    protected function amountTotal(Builder $query): string
    {
        $total = $query->notCancelled()->reorder()->toBase()->selectRaw('coalesce(sum(amount), 0)::numeric(14, 2)::text as total')->value('total');

        return is_string($total) ? $total : self::ZERO;
    }
}
