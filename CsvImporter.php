<?php

namespace CsvImporter;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Module\BaseModule;

class CsvImporter extends BaseModule
{
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Model/*',
                __DIR__.'/Tests/*',
                __DIR__.'/Service/*',
                __DIR__.'/Command/*',
                __DIR__.'/Controller/*',
                __DIR__.'/Hook/*',
            ])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
