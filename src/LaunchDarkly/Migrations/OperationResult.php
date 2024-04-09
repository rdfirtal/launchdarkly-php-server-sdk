<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use Exception;
use LaunchDarkly\Types\Result;

/**
 * The OperationResult pairs an origin with a result.
 */
class OperationResult
{
    public $value;
    public ?string $error;
    public ?Exception $exception;

    public Origin $origin;
    private Result $result;

    public function __construct(
        Origin $origin,
        Result $result
    ) {
        $this->value = $result->value;
        $this->error = $result->error;
        $this->exception = $result->exception;
        $this->origin = $origin;
        $this->result = $result;
    }

    /**
     * Determine whether this result represents success or failure.
     */
    public function isSuccessful(): bool
    {
        return $this->result->isSuccessful();
    }
}
