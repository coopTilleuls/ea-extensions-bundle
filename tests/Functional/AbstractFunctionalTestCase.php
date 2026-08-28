<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

abstract class AbstractFunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Force the router to load the "easyadmin.routes" resource, which populates the
        // dashboard/CRUD-controller cache that AdminUrlGenerator reads from — otherwise it's
        // still empty at this point, since nothing has made a real HTTP request yet.
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);
        $router->getRouteCollection();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @param array<string, string> $extraParams
     */
    protected function crudUrl(string $crudControllerFqcn, string $action = Action::INDEX, array $extraParams = []): string
    {
        /** @var AdminUrlGeneratorInterface $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGeneratorInterface::class);
        $generator = $adminUrlGenerator
            ->setController($crudControllerFqcn)
            ->setAction($action)
        ;

        foreach ($extraParams as $name => $value) {
            $generator->set($name, $value);
        }

        return $generator->generateUrl();
    }
}
