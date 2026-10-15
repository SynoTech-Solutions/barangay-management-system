<?php

namespace App\Services\Sms;

final class RecipientSet
{
    /**
     * @var array<string, SmsRecipient>
     */
    private array $recipients = [];

    private int $skipped = 0;

    private int $duplicates = 0;

    private int $matched = 0;

    public function add(SmsRecipient $recipient): void
    {
        $this->matched++;

        if (isset($this->recipients[$recipient->number])) {
            $this->duplicates++;

            return;
        }

        $this->recipients[$recipient->number] = $recipient;
    }

    public function skip(int $count = 1): void
    {
        $this->matched += $count;
        $this->skipped += $count;
    }

    public function merge(self $other): self
    {
        $merged = clone $this;
        foreach ($other->recipients as $recipient) {
            $merged->add($recipient);
        }
        $merged->matched += $other->skipped + $other->duplicates;
        $merged->skipped += $other->skipped;
        $merged->duplicates += $other->duplicates;

        return $merged;
    }

    /**
     * @return list<SmsRecipient>
     */
    public function all(): array
    {
        return array_values($this->recipients);
    }

    public function count(): int
    {
        return count($this->recipients);
    }

    public function isEmpty(): bool
    {
        return $this->recipients === [];
    }

    public function skipped(): int
    {
        return $this->skipped;
    }

    public function duplicates(): int
    {
        return $this->duplicates;
    }

    public function matched(): int
    {
        return $this->matched;
    }

    /**
     * @return array{matched: int, recipients: int, skipped: int, duplicates: int}
     */
    public function summary(): array
    {
        return [
            'matched' => $this->matched,
            'recipients' => $this->count(),
            'skipped' => $this->skipped,
            'duplicates' => $this->duplicates,
        ];
    }
}
