<?php

declare(strict_types=1);

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Controller\CrudExportDownloadController;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportSettings;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester\CrudExportRequesterResolverInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester\SecurityUserCrudExportRequesterResolver;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx\CsvToXlsxConverter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
    ;

    // CrudExportFieldResolverInterface.php and Format/CrudExportFormatWriterInterface.php are
    // deliberately NOT excluded: Symfony only reads their #[AutoconfigureTag] attribute (which
    // makes any implementor autowireable via the matching AutowireLocator) from interfaces it
    // has a Definition for — i.e. ones this loader actually saw. Interfaces are never
    // instantiated regardless, and Format/{Csv,Xlsx}CrudExportFormatWriter.php are the two
    // built-in implementations, so they must stay in to be registered as services.
    $services->load('CoopTilleuls\\Bundle\\EAExtensionsBundle\\CrudExport\\', __DIR__.'/../src/CrudExport/')
        ->exclude(__DIR__.'/../src/CrudExport/{Controller,Event,Message,CrudExportColumn.php,CrudExportAction.php,CrudExportFormat.php,CrudExportSettings.php,CrudExportConfigurationInterface.php,Password/CrudExportPasswordProviderInterface.php,Requester/CrudExportRequesterResolverInterface.php,Xlsx/CsvToXlsxConverter.php}')
    ;

    $services->set(CrudExportDownloadController::class)
        ->tag('controller.service_arguments')
        ->public()
    ;

    $services->set(CrudExportSettings::class)
        ->args([
            '$asyncThreshold' => '%coop_tilleuls_ea_extensions.crud_export.async_threshold%',
            '$maxLimit' => '%coop_tilleuls_ea_extensions.crud_export.max_limit%',
            '$xlsxEnabled' => '%coop_tilleuls_ea_extensions.crud_export.xlsx.enabled%',
            '$xlsxEncryptionEnabled' => '%coop_tilleuls_ea_extensions.crud_export.xlsx.encryption_enabled%',
            '$csv2xlsxBinary' => '%coop_tilleuls_ea_extensions.crud_export.xlsx.csv2xlsx_binary%',
        ])
    ;

    $services->set(CsvToXlsxConverter::class)
        ->args(['$csv2xlsxBinary' => '%coop_tilleuls_ea_extensions.crud_export.xlsx.csv2xlsx_binary%'])
    ;

    $services->alias(CrudExportRequesterResolverInterface::class, SecurityUserCrudExportRequesterResolver::class);
};
