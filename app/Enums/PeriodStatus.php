<?php

namespace App\Enums;

enum PeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Finalized = 'finalized';
    case Locked = 'locked';
    case Archived = 'archived';

    /**
     * Statuses in which meals and other entries may be created or edited.
     */
    public function acceptsEntries(): bool
    {
        return $this === self::Open;
    }

    /**
     * Statuses in which pending items may still be approved or rejected.
     */
    public function acceptsApprovals(): bool
    {
        return in_array($this, [self::Open, self::Reviewing], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::Reviewing],
            self::Reviewing => [self::Open, self::Finalized],
            self::Finalized => [self::Locked],
            self::Locked => [self::Archived],
            self::Archived => [],
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Finalized, self::Locked, self::Archived], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Open => 'success',
            self::Reviewing => 'warning',
            self::Finalized => 'primary',
            self::Locked, self::Archived => 'dark',
        };
    }
}
