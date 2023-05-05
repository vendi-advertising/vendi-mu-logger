<?php

namespace Vendi\Logger;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class ConstantInfoAttribute
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $defaultValue = null,
    ) {
    }
}