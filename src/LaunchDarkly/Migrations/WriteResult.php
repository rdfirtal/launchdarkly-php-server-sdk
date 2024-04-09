<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * The WriteResult pairs an origin with a result.
 */
class WriteResult
{
    public OperationResult $authoritative;
    public ?OperationResult $nonauthoritative;

    public function __construct(
        OperationResult $authoritative,
        ?OperationResult $nonauthoritative = null
    ) {
        $this->authoritative = $authoritative;
        $this->nonauthoritative = $nonauthoritative;
    }
}
