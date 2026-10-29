<?php

namespace ErnestDefoe\Herald\Sending;

/**
 * One person a mailing goes to: a member, or a guest newsletter subscriber.
 */
final class Recipient
{
    /**
     * @param array<string, string> $values their quick-tag values
     */
    public function __construct(
        public readonly bool $guest,
        public readonly int $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $locale,
        public readonly array $values,
        public readonly int $attempts = 0,
    ) {
    }

    /** The delivery row's column for this recipient. */
    public function column(): string
    {
        return $this->guest ? 'subscriber_id' : 'user_id';
    }
}
