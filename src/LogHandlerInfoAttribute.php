<?php

namespace Vendi\Logger;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class LogHandlerInfoAttribute
{
    public function __construct(
        public readonly string $group,
        public readonly string $description,
        public readonly ?bool $disabledIfNotSet = null,
        public readonly ?string $defaultValue = null,
    ) {
    }
}