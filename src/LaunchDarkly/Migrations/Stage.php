<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

class Stage
{
    public const OFF = 'off';
    public const DUALWRITE = 'dualwrite';
    public const SHADOW = 'shadow';
    public const LIVE = 'live';
    public const RAMPDOWN = 'rampdown';
    public const COMPLETE = 'complete';
}
