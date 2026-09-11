# CRUD index export

Adds an "Exporter" action to any CRUD's index page, exporting exactly the rows currently
matched by the visitor's filters/search (CSV by default, XLSX optionally) as a synchronous
download or, for large result sets, an asynchronous background job. The current sort order
is **not** preserved — the export query drops it and returns rows unordered — only the
filter/search criteria carry over. Works on any `AbstractCrudController`.

Unlike the bundle's other features, this one is **disabled by default**: it has a mandatory
setting (`storage_service_id`, see below) with no bundle-owned default, so enabling it
unconditionally would break `composer update`/`docker compose up` for any app that hasn't
configured it yet. Enable it explicitly once storage is configured:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    crud_export:
        enabled: true
```

## Consumer app setup (one-time)

0. `league/flysystem`, `symfony/messenger` and `symfony/process` are suggested, not
   required, by the bundle — apps that never enable this feature don't carry their weight.
   Install them before enabling it:
   ```bash
   composer require league/flysystem symfony/messenger symfony/process
   ```
   `symfony/process` is needed even if XLSX export itself stays disabled (it backs the
   CSV-to-XLSX conversion service, which is always wired once this feature is enabled).
   Enabling `crud_export` without one of these installed fails fast at container
   compilation with a `LogicException` naming the missing package.
1. Import the bundle's routes (the download endpoint for async exports):
   ```yaml
   # config/routes/ea_extensions.yaml
   ea_extensions_crud_export:
       resource: '@EAExtensionsBundle/src/CrudExport/Controller/'
       type: attribute
   ```
2. Enable the feature and point `storage_service_id` at a Flysystem storage the app
   already has (or a new one) — there is no bundle-provided default, so the app must be
   explicit about where exported files live:
   ```yaml
   coop_tilleuls_ea_extensions:
       crud_export:
           enabled: true
           storage_service_id: 'export.crud_export' # any service implementing League\Flysystem\FilesystemOperator
   ```
3. Route `CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage`
   to whichever transport should handle background exports — the bundle intentionally
   pins none:
   ```yaml
   framework:
       messenger:
           routing:
               'CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Message\ExportCrudMessage': async
   ```
   `ExportCrudMessageHandler` (tagged `#[AsMessageHandler]`, autowired by the bundle) picks
   the message up automatically **once something actually consumes that transport** — a
   `messenger:consume async` worker must be running (systemd/supervisor unit, cron `*/1
   * * * * ... --time-limit=55`, or a `doctrine://` transport read by an existing worker),
   exactly like any other Symfony Messenger message. Without a running consumer, exports
   above `async_threshold` stay queued and are never produced.
4. Tune the sync/async/rejection thresholds if the defaults (1000 / 50000 rows) don't fit:
   ```yaml
   coop_tilleuls_ea_extensions:
       crud_export:
           async_threshold: '%env(int:CRUD_EXPORT_ASYNC_THRESHOLD)%'
           max_limit: '%env(int:CRUD_EXPORT_MAX_LIMIT)%'
   ```

## Basic usage

Nothing to do — the "Exporter" button appears on every index page automatically.

- Below `async_threshold` rows: the file downloads immediately.
- At or above `async_threshold` (and at or below `max_limit`): the export runs in the
  background; the visitor is redirected back with a flash message.
- Above `max_limit`: the export is rejected with a flash message asking to narrow the
  filters.

## Restricting, disabling, or naming exports per controller

Implement `CrudExportConfigurationInterface` on a CRUD controller — entirely optional,
its absence just means "enabled, same permission as the index page, CSV, name from the
entity label":

```php
final class ProductCrudController extends AbstractCrudController implements CrudExportConfigurationInterface
{
    public static function isExportEnabled(): bool
    {
        return true; // false removes the "Exporter" button AND ignores the export flag
                      // even if requested directly through the URL — use this to opt a
                      // specific controller out, instead of the bundle-wide
                      // `crud_export.enabled` flag which turns it off everywhere.
    }

    public static function getExportPermission(): string|Expression|null
    {
        return new Expression('is_granted("EXPORT_PRODUCT")');
    }

    public static function getExportName(AdminContext $context): ?string
    {
        // $context carries the current filters/search/entity, so the name can vary
        // with what's actually being exported instead of being a fixed string.
        return 'products-'.($context->getRequest()->query->get('categoryId') ?? 'all');
    }

    public static function getExportFormat(): string
    {
        return CrudExportFormat::CSV; // or ::XLSX, or your own format key — see below
    }

    public static function getExportMode(): ?CrudExportMode
    {
        return null; // or CrudExportMode::Sync / ::Async to force one, ignoring
                      // async_threshold for this controller — max_limit still applies
                      // as a hard rejection ceiling either way.
    }
}
```

## Customizing the CSV output and the action button

```yaml
coop_tilleuls_ea_extensions:
    crud_export:
        action_icon: 'fa fa-file-export' # any Font Awesome class, default 'fa fa-download'
        csv:
            separator: ';'    # default ','
            with_bom: true    # default false — prepends a UTF-8 BOM (e.g. for Excel on Windows)
```

These apply to the built-in CSV writer only (`CrudExportFormat::CSV`); the intermediate CSV
XLSX export converts from is unaffected.

## Per-field custom export values

By default a column exports its property's getter/raw value (relations are traversed via
dotted paths, e.g. `category.name`; translatable/date/collection values are formatted
automatically). To fully control one column's exported value, tag a service and reference
it from `configureFields()`:

```php
final class PriceExportResolver implements CrudExportFieldResolverInterface
{
    public function __invoke(object $entity): mixed
    {
        return sprintf('%.2f €', $entity->getPriceCents() / 100);
    }
}
```

```php
MoneyField::new('priceCents')
    ->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, PriceExportResolver::class);
```

## CSV vs. XLSX

CSV by default. XLSX is opt-in and reuses the `csv2xlsx` binary
(https://github.com/mentax/csv2xlsx) — it must be installed and on `$PATH` on the
server (or point `csv2xlsx_binary` at its path):

```yaml
coop_tilleuls_ea_extensions:
    crud_export:
        xlsx:
            enabled: true
            csv2xlsx_binary: '/usr/local/bin/csv2xlsx'
```

A controller then opts into XLSX via `getExportFormat(): CrudExportFormat::XLSX` (see
above). The format is fixed per controller by configuration — there is no runtime
CSV/XLSX picker.

## Adding a custom export format

CSV and XLSX are the two built-in implementations of
`CrudExportFormatWriterInterface`, resolved by a key that matches `getExportFormat()`.
Register your own implementation to support any other format (TXT, JSON, whatever a specific integration needs):

```php
final class TxtCrudExportFormatWriter implements CrudExportFormatWriterInterface
{
    public static function getFormatKey(): string
    {
        return 'txt'; // whatever string getExportFormat() returns to opt in
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    public function write(array $headers, iterable $rows, string $tmpBasePath, string $crudControllerFqcn, string $requesterId): string
    {
        $path = $tmpBasePath.'.txt';
        $handle = fopen($path, 'wb');
        fwrite($handle, implode("\t", $headers)."\n");
        foreach ($rows as $row) {
            fwrite($handle, implode("\t", $row)."\n");
        }
        fclose($handle);

        return $path;
    }
}
```

No further wiring needed — the class is autoconfigured (tagged like any implementation
of an `#[AutoconfigureTag]` interface in this bundle) and picked up by format key the
same way `CsvCrudExportFormatWriter`/`XlsxCrudExportFormatWriter` are. A controller then
opts in with `getExportFormat(): string { return 'txt'; }`. `$tmpBasePath` is a unique,
extension-less temp path — append your own suffix to it (as above) and return the
resulting file's absolute path; the caller uploads it to storage and deletes it, you're
only responsible for cleaning up any *other* intermediate file you create (the way
`XlsxCrudExportFormatWriter` deletes its intermediate `.csv` once converted).

## Optional password protection (XLSX only)

No implementation registered = never encrypted, regardless of the flag below. To enable:

```yaml
coop_tilleuls_ea_extensions:
    crud_export:
        xlsx:
            encryption_enabled: true
```

```php
final class UserPassphraseCrudExportPasswordProvider implements CrudExportPasswordProviderInterface
{
    public function resolvePassword(string $crudControllerFqcn, string $requesterId): ?string
    {
        return $this->userRepository->findByIdentifier($requesterId)?->getExportPassphrase();
    }
}
```

Register it and alias it to `CrudExportPasswordProviderInterface`.

## Reacting to a finished or failed export

The bundle sends no notification itself — it dispatches events instead:

- `CrudExportAsyncJobStartingEvent` — fired at the very start of the async message
  handler, before the export query runs. A Messenger worker is a separate process from
  the HTTP request that triggered the export, so anything that request's own listeners set
  up (most notably an **enabled Doctrine filter**, e.g. a soft-deleteable filter) is not
  automatically present here. Listen to this event to re-enable it:
  ```php
  #[AsEventListener(event: CrudExportAsyncJobStartingEvent::class)]
  final class ReEnableSoftDeleteableFilter
  {
      public function __construct(private readonly EntityManagerInterface $em) {}

      public function __invoke(CrudExportAsyncJobStartingEvent $event): void
      {
          $this->em->getFilters()->enable('softdeleteable');
      }
  }
  ```
- `CrudExportFileReadyEvent` — fired for both sync and async exports, once the file is
  written to storage. Carries `downloadUrl` (only set for async exports).
- `CrudExportFailedEvent` — fired when an async export throws, right before the exception
  is rethrown (Messenger's own retry/failure-transport handling still applies).

```php
#[AsEventListener(event: CrudExportFileReadyEvent::class)]
final class NotifyUserOfExport
{
    public function __invoke(CrudExportFileReadyEvent $event): void
    {
        if ($event->async) {
            $this->notifier->send($event->requesterId, $event->exportName, $event->downloadUrl);
        }
    }
}
```

## Interaction with CustomizableColumns

If the bundle's `customizable_columns` feature is also enabled, an export triggered from
an index page reflects exactly the columns the visitor currently has selected/visible —
no configuration needed on either side. If it's disabled (or not installed), export simply
falls back to the controller's default `configureFields()` set.
