---
name: bundle-ci-scaffolding
description: How to scaffold a new standalone coopTilleuls Symfony bundle with a working PHPUnit/PHPStan/PHP-CS-Fixer/Rector CI pipeline — repo/submodule wiring and the composer/test-kernel gotchas hit doing it for EAExtensionsBundle
---

# Scaffolding a new coopTilleuls bundle + CI

This is the generic procedure — distinct from `theme-bundle-workflow`, which covers `ThemeBundle`'s own three-repo dev/verify loop, not how to bootstrap a *new* bundle from nothing.

## Repo / submodule pattern

New standalone bundle → own private GitHub repo under the `coopTilleuls` org → wired into `cgt-test` as a git submodule at `src/<Namespace>/Bundle/<BundleName>` (same as `ThemeBundle` at `src/CGT/Bundle/ThemeBundle`).

Sequence that actually works:
1. Scaffold the bundle's files directly at the target path (plain directory, no git yet).
2. `git init -b main`, commit, `git remote add origin <url>`, push — get it live as a real, non-empty repo first.
3. From the `cgt-test` root: `rm -rf` that directory, then `git submodule add <url> <path>`. Adding a submodule over a directory that's already populated (but not itself set up as that exact remote's checkout) doesn't register cleanly — wiping and re-cloning via `submodule add` is the reliable path to a correct `.gitmodules` entry + gitlink.

**Gitlink staleness gotcha**: if you push a follow-up fix commit to the submodule *after* already `git add`-ing it in the superproject, the superproject's recorded gitlink can go stale — `git status` in the superproject shows the submodule path as modified with "(nouveaux commits)". Re-`git add <submodule-path>` before the superproject commit (or amend) to pick up the submodule's actual current HEAD.

## CI/tooling convention

For a new bundle's PHPUnit/PHPStan/PHP-CS-Fixer/Rector setup, mirror **`cgt-cogitiel`**'s rulesets — confirmed explicitly by the user as the source of truth for this. Do NOT mirror `cgt-test` itself (the app has zero tooling configured — no PHPUnit/PHPStan/CS-Fixer/CI at all). `ThemeBundle` (`cgt-theme-bundle`) *does* now have its own CI too (added 2026-08-25 — Playwright screenshot-regression tests, not PHPUnit, but the same PHPStan/CS-Fixer/Rector/PHP-8.4-floor scaffolding applies) — see its own copy of this skill for what differs (a fixture *app*, not just a fixture kernel, since it needs to actually render themed pages for screenshots).

Reuse from cgt-cogitiel:
- **PHPStan**: level 8, `phpstan/phpstan-symfony` + `phpstan/extension-installer`.
- **PHP-CS-Fixer**: `@Symfony` ruleset. Swap cogitiel's `@PHP83Migration` for `@PHP82Migration` (or whatever matches the bundle's actual minimum PHP — don't blindly copy the app's PHP floor).
- **Rector**: same `withSkip` rule list, `withImportNames(importShortClasses: false)`, `withPreparedSets(deadCode: true, typeDeclarations: true)`, `withCodeQualityLevel(15)`, same cache/parallel settings. Adapt `withComposerBased(...)` flags to the bundle's actual deps (a fresh bundle likely has no Twig/Doctrine dependency yet → `false`).

Skip entirely: cogitiel's Docker/Keycloak/Postgres/paratest/Makefile machinery. That's all app-runtime infrastructure — a standalone bundle has no services to boot, so its CI is plain `shivammathur/setup-php` + `ramsey/composer-install` + `vendor/bin/<tool>`, no `docker compose` anywhere. cogitiel also only runs Rector via `make rector-dry` / an auto-fix bot, never as a `ci.yml` gate — for a small bundle with no auto-fix-bot infra, just add `rector process --dry-run` as a normal CI job instead.

## Symfony-8-leak gotcha

Declaring `"symfony/framework-bundle": "^6.4|^7.0"` in `require-dev` does **not** keep the whole dev/test dependency tree off Symfony 8. Recent `framework-bundle` releases already widen their own internal constraints (e.g. `symfony/routing: ^7.4|^8.0`), so unconstrained transitive components — `routing`, `http-foundation`, `cache`, `console`, `string`, `var-dumper`, `var-exporter`, `error-handler`, `event-dispatcher`, `filesystem`, `finder`, `options-resolver`, `process`, `stopwatch`, `dom-crawler` — silently resolve to 8.x, which requires PHP ≥8.4 and breaks any CI matrix cell on a lower PHP version.

Fix: add an explicit `conflict` entry blocking each of those at `">=8.0"`:

```json
"conflict": {
    "symfony/cache": ">=8.0",
    "symfony/console": ">=8.0",
    "symfony/dom-crawler": ">=8.0",
    "symfony/error-handler": ">=8.0",
    "symfony/event-dispatcher": ">=8.0",
    "symfony/filesystem": ">=8.0",
    "symfony/finder": ">=8.0",
    "symfony/http-foundation": ">=8.0",
    "symfony/options-resolver": ">=8.0",
    "symfony/process": ">=8.0",
    "symfony/routing": ">=8.0",
    "symfony/stopwatch": ">=8.0",
    "symfony/string": ">=8.0",
    "symfony/var-dumper": ">=8.0",
    "symfony/var-exporter": ">=8.0"
}
```

Verify with `composer why-not php <lowest-supported-version>` after install — it'll name exactly which resolved package is the blocker if this leaks again.

## easycorp/easyadmin-bundle 5.5 genuinely requires PHP ≥8.4

If the bundle depends on `easycorp/easyadmin-bundle`, don't bother trying to keep PHP 8.2/8.3 support (skip the whole conflict-list dance above for this specific case) — just set `"php": ">=8.4"` directly. Reason: EasyAdmin 5.5.1's own UI (sidebar, action menus, modals, tabs — all its "anonymous" Twig components with no PHP-backed class) only renders with `symfony/ux-twig-component` ^3.0, which itself requires PHP ≥8.4. EasyAdmin's `composer.json` claims `^2.32|^3.0` compatibility, but on the 2.x line no `anonymous_template_directory` is configured for EasyAdmin's own component namespace, so every anonymous component 404s with "Unknown component ... And no matching anonymous component template was found." This was verified empirically (not from docs) while building `EAExtensionsBundle`'s functional tests — confirmed by temporarily allowing `ux-twig-component` 3.x and watching the exact same page render correctly.

**Standing project policy (2026-08-26, stated directly by the user — "on va partir sur 8.4 pour tout le monde"): every coopTilleuls repo that depends on `easycorp/easyadmin-bundle` requires PHP ≥8.4 outright, drops any `conflict` block, and runs CI on a single PHP 8.4 matrix (`highest` + `lowest` deps, no more per-PHP-version cells).** This isn't just "for new bundles" — `ea-extensions-bundle` itself was migrated off its original `>=8.3` + `conflict`-block setup to this once a fresh `composer why-not php 8.3` showed `symfony/ux-twig-component`/`symfony/form`/`doctrine/instantiator` had each independently drifted to require PHP 8.4 anyway (see the conflict-list gotcha above — it's whack-a-mole against every transitive dependency that might bump its own floor, not just the Symfony components the list happens to name). `cgt-theme-bundle` and the `cgt-test` app's own `composer.json` were bumped to `>=8.4` the same day for consistency with what their Dockerfiles already ran. When touching CI/composer.json on any of these repos, treat `>=8.4` + no conflict-list as the current baseline, not `>=8.3` + conflict-list.

**Dropping the conflict-list re-exposes a second, separate PHP-8.4-adjacent trap**: `symfony/var-exporter` ≥8.0 removes the reflection-based `LazyGhostTrait` that Doctrine ORM's proxy factory (this version) still relies on, and PHP 8.4's own native-lazy-objects support isn't wired into Doctrine here either — the container throws `"Symfony LazyGhost is not available. Please install the symfony/var-exporter package version 6.4 or 7 ... or enable PHP 8.4 native lazy objects."` at the first entity-manager use. Hit identically on both `cgt-theme-bundle` and `ea-extensions-bundle` once each dropped its conflict-list. Fix: pin `"symfony/var-exporter": "^7.2.5"` explicitly in `require`/`require-dev` (7.2.5+ is the first version with the PHP-8.4-safe nullable-parameter signatures, and it's the newest pre-8.0 release) — this is a permanent pin, not a workaround to remove later.

**A Rector auto-modernization can pass on `highest`-deps CI but silently break at runtime on `lowest`-deps.** `GetFiltersAndFunctionsToAsTwigAttributeRector` (from `withComposerBased(symfony: true)`) rewrites a `Twig\Extension\AbstractExtension`+`getFunctions()` class to the newer `#[AsTwigFunction]` attribute form — correct and clean on `highest` deps, but the attribute-based registration needs a newer `symfony/twig-bridge`/`twig/twig` than this bundle's declared floor guarantees. Under `lowest`-deps the function silently never gets registered (`Unknown "..." function in "..."` at render time, not at CI-check time, since the CI `rector`/`phpstan`/`cs-fixer` jobs only ever install `highest` deps). Confirmed as a real regression (not theoretical) by running the actual `lowest`-deps PHPUnit suite after applying the rule. General lesson: any Rector rule that swaps to a newer PHP/library *feature* (not just newer *syntax*) needs verifying against the `lowest`-deps install too, not just `--dry-run` on default (`highest`) deps — if it breaks lowest-deps, add it to `rector.php`'s `withSkip([...])` with a comment explaining why, don't just apply it because CI was green.

## EasyAdmin-specific test-kernel wiring

Beyond the generic recipe below, a functional test kernel that actually renders EasyAdmin pages (not just boots) needs several more pieces, each discovered by hitting a real error:

- **Dashboard controller**: EasyAdmin 5.5 requires the `#[AdminDashboard(routePath: '/admin', routeName: 'admin')]` attribute on the class — a plain Symfony `#[Route('/admin')]` on `index()` throws `"... must apply the #[AdminDashboard] attribute ..."` at request time, not at boot.
- **Routes**: import with `$routes->import('.', 'easyadmin.routes')` (the resource argument is ignored, only the `type` matters) — a plain `attribute`-type import of the dashboard controller does NOT wire EasyAdmin's own dynamic per-CRUD-action routing (the `EA::ROUTE_CREATED_BY_EASYADMIN` request attribute never gets set, so `AdminRouterSubscriber` silently no-ops and every `?crudControllerFqcn=...` request just renders the plain dashboard welcome page).
- **`TwigComponentBundle` + `TwigExtraBundle`** must both be registered (`registerBundles()`), even though neither is *your* bundle's direct dependency — they're `easycorp/easyadmin-bundle`'s own required deps, and its templates use `<twig:ea:...>` components (needs `TwigComponentBundle`) and the `html_classes()` function from `twig/html-extra` (needs `TwigExtraBundle` to register that Twig extension — installing the package alone isn't enough).
- **`twig_component.anonymous_template_directory`** must be set explicitly (`'components'`) — required config on `ux-twig-component` ^3.0, no default, container compilation fails without it.
- **CSRF + session**: EasyAdmin's own confirmation-modal template calls Twig's `csrf_token()`, which throws `"CSRF tokens can only be generated if a CsrfTokenManagerInterface is injected..."` unless `framework.csrf_protection: true`; that in turn throws `"CSRF protection needs sessions to be enabled."` unless `framework.session` is also configured (plain `true` is enough — the test-mode session listener swap happens automatically via `framework.test: true`, no need to hand-pick a mock storage factory).
- **Firewall needs a `logout` config** (e.g. `path: /logout`) — EasyAdmin's layout template renders a logout link via `logout_url()`, which throws `"Unable to find logout in the current firewall"` if the firewall has none, even if you never actually visit that URL in a test.
- **`AdminUrlGeneratorInterface` needs the router primed first**: calling it directly from test code (not from inside a real dispatched request) throws `"... is not a valid Dashboard controller ..."` because the `AdminControllerRegistry` cache it reads from is only populated when EasyAdmin's route loader actually runs, which Symfony's router does lazily. Fix: call `$container->get(RouterInterface::class)->getRouteCollection()` once (e.g. in test `setUp()`) before ever calling `AdminUrlGenerator`.
- **`WebTestCase::loginUser()` pitfall**: if the user class implements `Symfony\Component\Security\Core\User\EquatableInterface` (e.g. `InMemoryUser` does), `ContextListener::refreshUser()` calls `isEqualTo()` to decide whether to trust the restored session token — and `InMemoryUser::isEqualTo()` does a **strict `getPassword()` comparison**. Constructing the login user with a `null` password while the configured provider's user has a real password string (`new InMemoryUser('admin', null, [...])` vs. `providers.in_memory.memory.users.admin.password: 'admin'`) silently deauthenticates the token on the very next request — `Security::getUser()` just returns `null`, no exception, no obvious error. Always pass the **same** password used in the provider config to `loginUser()`'s user object.

## Bundle test-kernel recipe

A minimal functional-test `Kernel` (in `tests/Fixtures/Kernel.php`) needs `Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait` — without it, `KernelInterface::registerContainerConfiguration` stays abstract and boot fatals.

If `phpunit.xml.dist` sets `failOnDeprecation="true"` (recommended), the **lowest-deps** CI matrix cell resolves a real Symfony 6.4, which emits deprecation notices for config options that default-change in 7.0. Set them explicitly in `configureContainer` to the 7.0 values to stay deprecation-free on both majors:

```php
$container->extension('framework', [
    'test' => true,
    'secret' => 'test',
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
]);
```

## "lowest deps" CI cell fails on vendor-only PHP 8.4 deprecations

Once the PHP floor is 8.4 (see above) but the Symfony range still spans back to `^6.4|^7.0`, the `dependency-versions: lowest` matrix cell resolves each component at its very first minor release — which predates the later patches those packages received adding explicit `?Type` nullable signatures ahead of PHP 8.4's own "implicitly nullable parameter" deprecation. Expect a wall of `Implicitly marking parameter $x as nullable is deprecated` notices from `symfony/css-selector`, `browser-kit`, `security-core`, etc. — none from the bundle's own code.

The failure isn't PHPUnit's native `failOnDeprecation` attribute (setting it `false` alone does nothing) — `symfony/phpunit-bridge` auto-bootstraps its own `DeprecationErrorHandler` via a Composer `autoload.files` entry the moment the package is installed, completely independent of anything in `phpunit.xml.dist`'s `<phpunit>` attributes, and its default mode fails the build on any such notice. Fix: set `SYMFONY_DEPRECATIONS_HELPER=weak` as a `<server>` value in `phpunit.xml.dist`'s `<php>` block (and set the native `failOnDeprecation="false"` too, since it's a separate, independently-enforced check).

## `composer.lock` can silently drift from `composer.json`'s declared constraints

After editing `require`/`require-dev` (e.g. adding new packages a feature needs —
`doctrine/orm`, `easycorp/easyadmin-bundle`, `symfony/messenger`, `league/flysystem`, …),
running `composer install` first can fail loudly with `"Required package ... is not
present in the lock file"` / `"... is in the lock file as vX but that does not satisfy
your constraint"` — the lock file may still carry versions from an *earlier* state of
`composer.json` (including transitive components that had drifted to Symfony 8.x before a
prior `conflict`-list cleanup, even though nothing currently in `composer.json` asks for
them). This is the expected, correct failure mode — `composer install` never resolves,
it only replays the lock file. Fix is simply `composer update` (not `install`) to let it
re-resolve from the current `composer.json`; the loud failure is the useful signal that
this is needed, not a sign something's broken.

## No local `composer` binary

This dev environment has PHP CLI available directly on the host (checked: 8.3.6) but no `composer` binary. Use the official image instead, always with `--user`, or vendor/lockfile end up root-owned and need `sudo` (unavailable non-interactively) or a throwaway `docker run ... alpine rm -f ...` to clean up:

```bash
docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 composer install --no-interaction
```

Once `vendor/` exists, run tools directly with the host `php` binary. To spot-check a specific PHP version (e.g. the CI matrix's floor), use `docker run --rm -v "$PWD":/app -w /app php:8.2-cli php vendor/bin/phpunit`.
