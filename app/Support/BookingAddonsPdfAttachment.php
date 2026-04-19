<?php

namespace App\Support;

class BookingAddonsPdfAttachment
{
    public function __construct(
        public readonly string $name,
        public readonly string $content,
    ) {
    }
}
