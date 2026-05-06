<?php

declare(strict_types=1);

/**
 * Minimal PHPUnit bootstrap. The unit-style tests in tests/Unit do not need
 * a Symfony kernel — they instantiate entities/services directly. We just need
 * Composer's autoloader on the path.
 */

require dirname(__DIR__) . '/vendor/autoload.php';
