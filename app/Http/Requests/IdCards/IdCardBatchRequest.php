<?php

namespace App\Http\Requests\IdCards;

use App\Http\Requests\Documents\DocumentBatchRequest;
use App\Models\IdCard;

class IdCardBatchRequest extends DocumentBatchRequest
{
    public const string CARD_IDS_FIELD = 'card_ids';

    protected function model(): string
    {
        return IdCard::class;
    }

    protected function idsField(): string
    {
        return self::CARD_IDS_FIELD;
    }
}
