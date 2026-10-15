<?php

namespace App\Queries;

use App\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Collection;

final readonly class SmsTemplateQuery
{
    /**
     * @return Collection<int, SmsTemplate>
     */
    public function all(): Collection
    {
        return SmsTemplate::query()->orderBy('title')->get();
    }

    /**
     * @return list<array{id: int, title: string, body: string}>
     */
    public function options(): array
    {
        return array_values($this->all()
            ->map(fn (SmsTemplate $template): array => ['id' => $template->id, 'title' => $template->title, 'body' => $template->body])
            ->all());
    }
}
