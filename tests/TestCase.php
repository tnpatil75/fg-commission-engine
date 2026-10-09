<?php

namespace Tests;

use App\Commission\CommissionService;
use App\Commission\Data\CalculationInput;
use App\Commission\Data\CalculationResult;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public const TEST_DATABASE = 'commission_test';

    /**
     * Refuses to boot unless the separate test database is configured. This runs before RefreshDatabase,
     * so a misconfiguration (e.g. cached config) can never wipe the demo database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($app->configurationIsCached() || ! $app->environment('testing') || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(
                "Refusing to run tests against database '{$database}'. Tests must use the separate "
                .self::TEST_DATABASE.' database configured in phpunit.xml. If configuration is cached, run '
                .'"php artisan config:clear" first.'
            );
        }

        return $app;
    }

    protected function calculate(
        string $segment,
        string $region,
        string $leadCompany,
        string $saleType,
        string $amount = '1000.00',
        string $currency = 'USD',
        ?string $plantReference = null,
        ?string $externalOrderReference = null,
    ): CalculationResult {
        return app(CommissionService::class)->calculate(CalculationInput::fromStrings(
            $segment, $region, $leadCompany, $saleType, $amount, $currency, $externalOrderReference, $plantReference,
        ));
    }
}
