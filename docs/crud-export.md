# CRUD index export

Adds an "Exporter" action to the index page of opted-in CRUD controllers, exporting exactly the rows currently
matched by the visitor's filters/search (CSV by default, XLSX optionally) as a synchronous
download or, for large result sets, an asynchronous background job. The current sort order
is **not** preserved — the export query drops it and returns rows unordered — only the
filter/search criteria carry over. Works on any `AbstractCrudController` implementing
`CrudExportConfigurationInterface` — export is **opt-in per controller** (see below).

**Disabled by default**, like every other feature in this bundle — this one additionally has a
mandatory setting (`storage_service_id`, see below) with no bundle-owned default, so enabling it
without configuring storage first would break at boot time. Enable it explicitly once storage is
configured:

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

Export is **opt-in per controller**: a controller gets the "Exporter" button only once it
implements `CrudExportConfigurationInterface`. `CrudExportConfigurationTrait` provides the
defaults, so opting in takes two lines:

```php
final class ProductCrudController extends AbstractCrudController implements CrudExportConfigurationInterface
{
    use CrudExportConfigurationTrait; // enabled, same permission as the index page, CSV,
                                      // name from the entity label, sync/async by row count
}
```

A controller not implementing the interface has no button, and the export flag is ignored
even if added to its URL by hand.

- Below `async_threshold` rows: the file downloads immediately.
- At or above `async_threshold` (and at or below `max_limit`): the export runs in the
  background; the visitor is redirected back with a flash message.
- Above `max_limit`: the export is rejected with a flash message asking to narrow the
  filters.

## Restricting, disabling, or naming exports per controller

Override only the trait methods whose default does not fit:

```php
final class ProductCrudController extends AbstractCrudController implements CrudExportConfigurationInterface
{
    use CrudExportConfigurationTrait;

    public static function isExportEnabled(): bool
    {
        return true; // false removes the "Exporter" button AND ignores the export flag
                      // even if requested directly through the URL — e.g. to switch
                      // export off on a condition.
    }

    public static function getExportPermission(): string|Expression|null
    {
        return new Expression('is_granted("EXPORT_PRODUCT")'); // null: same as the index page
    }

    public static function getExportName(AdminContext $context): ?string
    {
        // $context carries the current filters/search/entity, so the name can vary
        // with what's actually being exported instead of being a fixed string.
        // Null falls back to the entity's plural label, lowercased.
        return 'products-'.($context->getRequest()->query->get('categoryId') ?? 'all');
    }

    public static function getExportFormat(): string
    {
        return CrudExportFormat::XLSX; // default ::CSV, or your own format key — see below
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

The "Exporter" action is always a primary action, rendered right after the controller's
default (secondary) global actions and before its other primary ones — whatever order the
controller gives its own actions.

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
    public function __invoke(object $entity, array $context): mixed
    {
        return sprintf('%.2f €', $entity->getPriceCents() / 100);
    }
}
```

```php
MoneyField::new('priceCents')
    ->setCustomOption(CrudExportColumn::OPTION_VALUE_RESOLVER, PriceExportResolver::class);
```

`$context` is the export context described below — empty unless the app registers a
`CrudExportContextProviderInterface`.

When the exported value only needs to come from another property or getter — typically a
column whose displayed value is computed with `formatValue()` — point the column at it
instead of writing a resolver:

```php
TextField::new('lastName')
    ->formatValue(static fn ($value, Author $author): string => $author->getDisplayName())
    ->setCustomOption(CrudExportColumn::OPTION_PROPERTY_PATH, 'displayName'); // getDisplayName()
```

The path accepts dotted relations (`'user.address.label'`) and is resolved like any other
column (getter, public property, translatable/date/collection formatting). A value resolver,
when also set, takes precedence.

## Carrying request context to async exports

An async export runs in a Messenger worker: no session, no security token, no current
request. A resolver whose value depends on *who* exports — e.g. a status computed relative
to the profile the visitor is currently acting under — cannot read that from a
request-scoped service there. Capture it instead while the request is still available, by
registering a context provider:

```php
final class CurrentProfileExportContextProvider implements CrudExportContextProviderInterface
{
    public function __construct(private readonly ProfileInterface $profile) {}

    public function provide(AdminContext $context): array
    {
        return ['profileId' => $this->profile->getCurrentId()];
    }
}
```

Alias it to `CrudExportContextProviderInterface`. The bundle calls it once per export, before
choosing sync or async, and hands the returned array unchanged to every field resolver
(`__invoke(object $entity, array $context)`) and to every event below (`$event->context`),
for sync and async exports alike — so a resolver behaves the same either way.

Return scalars only (`array<string, scalar|null>`): the context travels inside
`ExportCrudMessage` and must survive whichever serializer the app's transport uses. Pass
identifiers and reload what you need from them, never entities.

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

## Reacting to a requested, finished or failed export

The bundle sends no notification itself — it dispatches events instead:

- `CrudExportRequestedEvent` — fired once the export is accepted (permission granted,
  `max_limit` not exceeded), still within the triggering request, right before the file is
  generated (sync) or the job is queued (async). Carries `rowCount` and `async` — e.g. to
  keep an audit log of who exported what.
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
- `CrudExportFailedEvent` — fired once an async export has definitively failed, when
  Messenger gives up and no retry is left. Not fired on each retry.

All four carry the export context (`$event->context`, see above) — e.g. to notify the
requester under the profile they exported from, not just as a user.

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
