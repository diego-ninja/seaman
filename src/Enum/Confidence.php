<?php

declare(strict_types=1);

// ABOUTME: Enum representing detection confidence levels.
// ABOUTME: Used by project type and framework detectors.

namespace Seaman\Enum;

enum Confidence: string
{
    case High = "high";
    case Medium = "medium";
    case Low = "low";
}
