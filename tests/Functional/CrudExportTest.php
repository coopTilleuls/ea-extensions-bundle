<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Functional;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\CrudExportAction;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Event\CrudExportFileReadyEvent;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage;
use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\MessageHandler\ExportCrudMessageHandler;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\Entity\FixtureItem;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureItemCrudController;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureItemCrudControllerDisabled;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureItemCrudControllerForcedAsync;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureItemCrudControllerForcedSync;
use CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Fixtures\FixtureItemCrudControllerWithConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class CrudExportTest extends AbstractFunctionalTestCase
{
    public function testSyncExportDownloadsCsv(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $this->createItems(2);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudController::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        // StreamedResponse content is only captured on the BrowserKit-internal response,
        // not on the raw HttpFoundation Response returned by getResponse().
        $content = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('ID', $content);
        self::assertStringContainsString('item-1', $content);
        // amount is exported through FixtureAmountExportResolver (cents -> "x.xx")
        self::assertStringContainsString('1.00', $content);
    }

    public function testExportAboveMaxLimitIsRejected(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        // async_threshold=3, max_limit=10 in the fixture kernel
        $this->createItems(11);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudController::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testExportAsyncDispatchesMessageAndRedirects(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        // async_threshold=3, max_limit=10 in the fixture kernel
        $this->createItems(5);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudController::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        self::assertResponseRedirects();

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->getSent();

        self::assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(ExportCrudMessage::class, $message);
        self::assertSame(FixtureItemCrudController::class, $message->crudControllerFqcn);

        // The above only proves the message was queued — dispatching to a transport never
        // runs the handler by itself, that requires a `messenger:consume` worker to pull it
        // off the transport (see docs/crud-export.md). Simulate that worker here by invoking
        // the handler directly, the same way Messenger::Worker would.
        $readyEvents = [];
        $listener = static function (CrudExportFileReadyEvent $event) use (&$readyEvents): void {
            $readyEvents[] = $event;
        };
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $eventDispatcher->addListener(CrudExportFileReadyEvent::class, $listener);

        /** @var ExportCrudMessageHandler $handler */
        $handler = static::getContainer()->get(ExportCrudMessageHandler::class);
        $handler($message);

        $eventDispatcher->removeListener(CrudExportFileReadyEvent::class, $listener);

        self::assertCount(1, $readyEvents);
        self::assertTrue($readyEvents[0]->async);
        self::assertNotNull($readyEvents[0]->downloadUrl);

        /** @var FilesystemOperator $storage */
        $storage = static::getContainer()->get('fixture.crud_export_storage');
        self::assertTrue($storage->fileExists($readyEvents[0]->storagePath));
    }

    public function testExportDeniedByCustomPermission(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $this->createItems(1);

        $this->expectException(AccessDeniedException::class);

        $this->client->catchExceptions(false);
        $this->client->request('GET', $this->crudUrl(FixtureItemCrudControllerWithConfiguration::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));
    }

    public function testExportDisabledPerControllerIgnoresTheFlagAndRendersIndexNormally(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $this->createItems(1);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudControllerDisabled::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        $response = $this->client->getResponse();
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }

    public function testExportModeForcedToAsyncBypassesRowCountThreshold(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        // Only 1 row, well below async_threshold=3 — would normally go sync.
        $this->createItems(1);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudControllerForcedAsync::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        self::assertResponseRedirects();

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(1, $transport->getSent());
    }

    public function testExportModeForcedToSyncBypassesRowCountThreshold(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        // 5 rows, at or above async_threshold=3 — would normally go async.
        $this->createItems(5);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudControllerForcedSync::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }

    public function testExportModeForcedToSyncStillRejectedAboveMaxLimit(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        // 11 rows > max_limit=10 — forcing sync must not bypass the hard ceiling.
        $this->createItems(11);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudControllerForcedSync::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }

    public function testDownloadRejectsMismatchedRequester(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $this->client->request('GET', '/_ea-extensions/crud-export/download/someone-else/export_x_20260101_000000.csv');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadServesFileOwnedByCurrentRequester(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'user', ['ROLE_USER']));

        $this->createItems(1);

        $this->client->request('GET', $this->crudUrl(FixtureItemCrudController::class, extraParams: [
            CrudExportAction::QUERY_PARAM_TRIGGER => '1',
        ]));
        $contentDisposition = $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertNotNull($contentDisposition);
        self::assertSame(1, preg_match('/filename="([^"]+)"/', $contentDisposition, $matches));
        $filename = $matches[1] ?? self::fail('Content-Disposition filename not found.');

        $this->client->request('GET', '/_ea-extensions/crud-export/download/user/'.$filename);

        self::assertResponseIsSuccessful();
    }

    private function createItems(int $count): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        for ($i = 1; $i <= $count; ++$i) {
            $item = new FixtureItem();
            $item->name = 'item-'.$i;
            $item->amount = 100 * $i;
            $em->persist($item);
        }

        $em->flush();
        $em->clear();
    }
}
