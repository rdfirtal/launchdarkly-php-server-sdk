<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use LaunchDarkly\Impl\Migrations\Executor;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDContext;

/**
 * Migrator is a class for performing a technology migration.
 *
 * This class is not intended to be instanced directly, but instead should be constructed
 * using the {@see MigratorBuilder}.
 */
class Migrator
{

    private LDClient $client;
    private ExecutionOrder $executionOrder;
    private MigrationConfig $readConfig;
    private MigrationConfig $writeConfig;
    private bool $trackLatency;
    private bool $trackErrors;

    /**
     * @param \LaunchDarkly\Migrations\ExecutionOrder::* $executionOrder
     */
    public function __construct(
        LDClient $client,
        $executionOrder,
        MigrationConfig $readConfig,
        MigrationConfig $writeConfig,
        bool $trackLatency,
        bool $trackErrors
    ) {
        $this->client = $client;
        $this->executionOrder = $executionOrder;
        $this->readConfig = $readConfig;
        $this->writeConfig = $writeConfig;
        $this->trackLatency = $trackLatency;
        $this->trackErrors = $trackErrors;
    }

    /**
     * Uses the provided flag key and context to execute a migration-backed read operation.
     * @param \LaunchDarkly\Migrations\Stage::* $defaultStage
     */
    public function read(
        string $key,
        LDContext $context,
        string $defaultStage,
        $payload = null
    ): OperationResult {
        $variationResult = $this->client->migrationVariation($key, $context, $defaultStage);
        /** @var Stage */
        $stage = $variationResult['stage'];
        /** @var OpTracker */
        $tracker = $variationResult['tracker'];
        $tracker->operation(Operation::READ);

        $old = new Executor(Origin::OLD, $this->readConfig->old, $tracker, $this->trackLatency, $this->trackErrors, $payload);
        $new = new Executor(Origin::NEW, $this->readConfig->new, $tracker, $this->trackLatency, $this->trackErrors, $payload);

        switch ($stage) {
            case Stage::OFF:
                $result = $old->run();
                break;
            case Stage::DUALWRITE:
                $result = $old->run();
                break;
            case Stage::SHADOW:
                $result = $this->readBoth($old, $new, $tracker);
                break;
            case Stage::LIVE:
                $result = $this->readBoth($new, $old, $tracker);
                break;
            case Stage::RAMPDOWN:
                $result = $new->run();
                break;
            case Stage::COMPLETE:
                $result = $new->run();
                break;
        }

        $this->client->trackMigrationOperation($tracker);

        return $result;
    }

    /**
     * Uses the provided flag key and context to execute a migration-backed write operation.
     * @param \LaunchDarkly\Migrations\Stage::* $defaultStage
     */
    public function write(
        string $key,
        LDContext $context,
        string $defaultStage,
        $payload = null
    ): WriteResult {
        $variationResult = $this->client->migrationVariation($key, $context, $defaultStage);
        /** @var Stage */
        $stage = $variationResult['stage'];
        /** @var OpTracker */
        $tracker = $variationResult['tracker'];
        $tracker->operation(Operation::WRITE);

        $old = new Executor(Origin::OLD, $this->writeConfig->old, $tracker, $this->trackLatency, $this->trackErrors, $payload);
        $new = new Executor(Origin::NEW, $this->writeConfig->new, $tracker, $this->trackLatency, $this->trackErrors, $payload);

        switch ($stage) {
            case Stage::OFF:
                $writeResult = new WriteResult($old->run());
                break;
            case Stage::DUALWRITE:
                $writeResult = $this->writeBoth($old, $new, $tracker);
                break;
            case Stage::SHADOW:
                $writeResult = $this->writeBoth($old, $new, $tracker);
                break;
            case Stage::LIVE:
                $writeResult = $this->writeBoth($new, $old, $tracker);
                break;
            case Stage::RAMPDOWN:
                $writeResult = $this->writeBoth($new, $old, $tracker);
                break;
            case Stage::COMPLETE:
                $writeResult = new WriteResult($new->run());
                break;
        }

        $this->client->trackMigrationOperation($tracker);

        return $writeResult;
    }

    private function readBoth(Executor $authoritative, Executor $nonauthoritative, OpTracker $tracker): OperationResult
    {
        if ($this->executionOrder == ExecutionOrder::RANDOM && Util::sample(2)) {
            $nonauthoritativeResult = $nonauthoritative->run();
            $authoritativeResult = $authoritative->run();
        } else {
            $authoritativeResult = $authoritative->run();
            $nonauthoritativeResult = $nonauthoritative->run();
        }

        if ($this->readConfig->comparison === null) {
            return $authoritativeResult;
        }

        if ($authoritativeResult->isSuccessful() && $nonauthoritativeResult->isSuccessful()) {
            $tracker->consistent(fn (): bool => ($this->readConfig->comparison)($authoritativeResult->value, $nonauthoritativeResult->value));
        }

        return $authoritativeResult;
    }

    private function writeBoth(Executor $authoritative, Executor $nonauthoritative, OpTracker $tracker): WriteResult
    {
        $authoritativeResult = $authoritative->run();
        $tracker->invoked($authoritative->origin);

        if (!$authoritativeResult->isSuccessful()) {
            return new WriteResult($authoritativeResult);
        }

        $nonauthoritativeResult = $nonauthoritative->run();
        $tracker->invoked($nonauthoritative->origin);

        return new WriteResult($authoritativeResult, $nonauthoritativeResult);
    }
}
