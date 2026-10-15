<?php

namespace App\Queries;

use App\Models\CedulaTemplate;
use Illuminate\Database\Eloquent\Collection;

final readonly class CedulaTemplateQuery
{
    /**
     * @return Collection<int, CedulaTemplate>
     */
    public function active(): Collection
    {
        return CedulaTemplate::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, CedulaTemplate>
     */
    public function all(): Collection
    {
        return CedulaTemplate::query()->orderByDesc('is_active')->orderBy('name')->get();
    }
}
