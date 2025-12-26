<?php

// ABOUTME: Exception thrown when Packagist API operations fail.
// ABOUTME: Covers connection errors, invalid responses, and API errors.

declare(strict_types=1);

namespace Seaman\Exception;

use RuntimeException;

final class PackagistException extends RuntimeException {}
