<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $repository = Env::getRepository();
        $repository->clear('DB_CONNECTION');
        $repository->clear('DB_DATABASE');

        return parent::createApplication();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
