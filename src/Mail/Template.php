<?php

namespace ErnestDefoe\Herald\Mail;

/**
 * A mailing rendered for one language, with its quick tags still in place.
 */
final class Template
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
    ) {
    }
}
