<?php

declare(strict_types=1);

namespace Seaman\Enum;

enum Confidence: string
{
    case High = "high";
    case Medium = "medium";
    case Low = "low";
}
