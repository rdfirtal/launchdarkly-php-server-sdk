<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Migrations;

use Closure;
use Exception;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\Migrations\OperationResult;
use LaunchDarkly\Migrations\OpTracker;
use LaunchDarkly\Migrations\Origin;
use LaunchDarkly\Types\Result;

/**
 * Utility class for executing migration operations while also tracking our
 * built-in migration measurements.
 */
class Executor
{
    private Closure $fn;
    private OpTracker $tracker;
    private bool $trackLatency;
    private bool $trackErrors;
    private $payload;
    private Origin $origin;

    /**
     * @param Closure(mixed): Result $fn
     */
    public function __construct(
        Closure $fn,
        OpTracker $tracker,
        bool $trackLatency,
        bool $trackErrors,
        $payload,
        Origin $origin
    ) {
        $this->fn = $fn;
        $this->tracker = $tracker;
        $this->trackLatency = $trackLatency;
        $this->trackErrors = $trackErrors;
        $this->payload = $payload;
        $this->origin = $origin;
    }

    public function run(): OperationResult
    {
        $start = Util::currentTimeUnixMillis();

        try {
            $result = ($this->fn)($this->payload);
        } catch (Exception $e) {
            $result = Result::error($e->getMessage(), $e);
        }

        if ($this->trackLatency) {
            $this->tracker->latency($this->origin, Util::currentTimeUnixMillis() - $start);
        }

        if ($this->trackErrors && !$result->isSuccessful()) {
            $this->tracker->error($this->origin);
        }

        $this->tracker->invoked($this->origin);

        return new OperationResult($this->origin, $result);
    }
}
