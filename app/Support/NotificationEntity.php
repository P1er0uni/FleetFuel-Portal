<?php

namespace App\Support;

/**
 * The entity an event is about, reduced to a stable hash used by the 1-hour
 * deduplication (D11): the same rule, event and entity inside an hour only
 * notify once. The hash depends on the morph alias and the record ID, so it is
 * stable across requests and never leaks the ID in the column.
 */
final readonly class NotificationEntity
{
    public function __construct(
        public string $type,
        public int $id,
    ) {}

    public static function make(string $type, int $id): self
    {
        return new self($type, $id);
    }

    public function entityHash(): string
    {
        return hash('sha256', $this->type.':'.$this->id);
    }
}
