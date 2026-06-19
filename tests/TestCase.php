<?php

namespace CF7Mautic\Tests;

use Brain\Monkey;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(
            \Mockery::getContainer()->mockery_getExpectationCount()
        );
        Monkey\tearDown();
        parent::tearDown();
    }
}
