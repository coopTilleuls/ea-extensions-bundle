---
name: easyadmin-bundle-feature-patterns
description: Design patterns and DI/PHP/testing gotchas for building a new EAExtensionsBundle feature on top of EasyAdmin 5.5 internals — hit while building CrudExport (CGTB-495). Distinct from bundle-ci-scaffolding (CI/composer setup) and the EasyAdmin-test-kernel-wiring section of that skill (booting/rendering EA pages in tests).
---

# Building a new EAExtensionsBundle feature

This bundle's convention (see `CrudFields`, `CustomizableColumns`, `CrudExport`) is: no
trait, no base class ever required on a consumer's `AbstractCrudController`. Everything
composes through EasyAdmin's own extension points (`ActionsExtensionInterface`,
`AfterCrudActionEvent`, Symfony DI) instead. The patterns below are how to actually pull
that off without the gotchas that cost real debugging time.

## Extension-point pattern: a service-locator keyed by a short string, not a class name

`CrudFieldResolverInterface`/`CrudExportFieldResolverInterface`-style hooks (a consumer
implements an interface, references it by **FQCN**) are the easy case — `#[AutowireLocator(SomeInterface::class)]`
defaults to keying the locator by service id, which equals the FQCN for anything
autoloaded via the bundle's own `load()`.

When the key needs to be a short, stable **string** the consumer controls instead (e.g.
an export *format* — `'csv'`, `'xlsx'`, or a consumer's own `'txt'`), use
`defaultIndexMethod`:

```php
#[AutowireLocator(CrudExportFormatWriterInterface::class, defaultIndexMethod: 'getFormatKey')]
private ServiceProviderInterface $formatWriterLocator;
```

where the interface declares `public static function getFormatKey(): string;` — every
implementor (built-in or third-party) is indexed by whatever it returns, and a consumer
adds a brand-new format with zero bundle changes: implement the interface, register it as
a service (autoconfigure picks it up), done. This is the actual mechanism behind "CSV and
XLSX aren't special-cased — they're just the two built-in implementations" (`CsvCrudExportFormatWriter`/`XlsxCrudExportFormatWriter`).

## `#[AutoconfigureTag]`-on-an-interface only works if that interface has a container Definition

Both locator patterns above rely on `#[AutoconfigureTag]` declared **on the interface
itself** (not on each implementor) so any autoconfigured class implementing it gets
auto-tagged. This is implemented by `RegisterAutoconfigureAttributesPass`, which only
processes classes/interfaces it finds in `$container->getDefinitions()` — i.e. ones the
PSR-4 loader actually saw and registered (interfaces get a `Definition` too, just an
`abstract: true` one that's never instantiated; that's enough).

**The trap**: reflexively adding an `#[AutoconfigureTag]`-carrying interface to a
`config/*_services.php`'s `->exclude(...)` glob (a reasonable-looking instinct — "it's
just an interface, no constructor to autowire, no harm excluding it") silently breaks
the whole mechanism. No implementor ever gets tagged, the locator resolves to an *empty*
`ServiceLocator`, and the only symptom is a runtime `ServiceNotFoundException: "... the
container inside ServiceLocator is a smaller service locator that is empty..."` the first
time something is actually looked up — no compile-time error, no hint about *why*.
Confirmed by hitting exactly this with `CrudExportFieldResolverInterface.php` after
excluding it "for safety" the same way `Password/CrudExportPasswordProviderInterface.php`
(a plain marker interface, no `#[AutoconfigureTag]`) is legitimately excluded.

**Rule**: only exclude a file from the feature's `load()` glob if it's a DTO with scalar
constructor args (autowiring would fail) or a plain marker/config interface with **no**
`#[AutoconfigureTag]`. Any interface carrying that attribute must stay in.

## Composing two *optional* features with zero compile-time coupling

`CrudExport` needs to reflect `CustomizableColumns`' column selection when that other
optional feature is also enabled — but `CrudExport` must keep working standalone if it
isn't (or isn't even installed on the current branch). The trick: both features' glue
classes listen on the **same** `AfterCrudActionEvent`, dispatched once after EasyAdmin's
own `index()` has already built the paginated `entities` collection and run
`FieldFactory::processFieldsForAll()` on it.

If `CustomizableColumnsFieldsSubscriber` runs first and mutates that collection's
`FieldCollection` (removing/reordering fields per the visitor's saved selection),
a later listener that reads the **same** `FieldCollection` off the first processed entity
(`$event->getResponseParameters()->get('entities')->first()->getFields()`) sees the
mutation for free — no service dependency on `CustomizableColumns`, no `class_exists()`
check, no config coupling. Falls back cleanly to a fresh `configureFields()` call when
that collection is empty (e.g. zero rows on the current page) or the other feature never
ran. Reuse this "read the shared request-scoped mutated state instead of recomputing" shape
for any future feature that wants to optionally layer on top of another one.

## Resolving a real controller instance outside EasyAdmin's own request cycle

A feature that needs to call the *target* CRUD controller's own (possibly overridden)
`configureFields()`/`createIndexQueryBuilder()` — not a generic default — from inside an
event listener (which only gets the FQCN via `AdminContext`, not an instance) should use:

```php
$controller = $this->controllerFactory->getCrudControllerInstance($crudControllerFqcn, Action::INDEX, $request);
```

(`EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory`, a public autowireable
service). It resolves the real, fully-autowired service instance the same way Symfony's
own controller-resolver would for that route — the sanctioned alternative to
`$container->get($crudControllerFqcn)` (which won't work for a non-public service id
anyway).

## PHP: nullsafe operator does not work with static `::` calls

`$maybeClassString?::someStaticMethod()` is a **parse error**
(`T_PAAMAYIM_NEKUDOTAYIM`), not a runtime null-check — `?->` only applies to instance
member access. For a nullable `class-string|null`, spell it out:

```php
$result = null !== $fqcn ? $fqcn::someStaticMethod() : null;
```

Hit this repeatedly wiring `CrudExportConfigurationInterface`'s optional static hooks
(`getExportPermission()`, `getExportFormat()`, `getExportName()`, …) — PHPStan's
`phpstan.parse` check does catch it, but only if you run PHPStan; it silently exists as
invalid syntax until then.

## Testing: `StreamedResponse` content isn't on `$client->getResponse()`

In a `KernelBrowser`/`WebTestCase` functional test, a controller/listener that returns a
`StreamedResponse` (this bundle's sync export download does) has its content captured via
an internal `ob_start()`/`sendContent()` dance in `HttpKernelBrowser::doRequest()` — but
that captured string only ends up on the **BrowserKit-internal** response, not on the raw
`Symfony\Component\HttpFoundation\Response` object `$client->getResponse()` returns
(`getContent()` on that one is whatever the controller set directly, i.e. nothing useful
for a streamed body). Use:

```php
$content = $this->client->getInternalResponse()->getContent();
```

`$client->getResponse()` is still correct for status code and headers — only the body
needs the internal-response variant.
