<?php

namespace App\Queries;

use App\Queries\Filters\ResidentListFilters;
use App\Services\Sms\RecipientSet;
use App\Services\Sms\SmsRecipientResolver;

final readonly class ResidentRecipientQuery
{
    public function __construct(
        private ResidentListQuery $residents,
        private SmsRecipientResolver $resolver,
    ) {}

    /**
     * @param  list<int>  $residentIds
     */
    public function resolve(?ResidentListFilters $filters, array $residentIds): RecipientSet
    {
        return $filters === null
            ? $this->resolver->fromResidentIds($residentIds)
            : $this->resolver->fromResidentQuery($this->residents->builder($filters));
    }
}
