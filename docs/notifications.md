# Notifications

A ready-to-use notification feature: a `Notification` entity, a CRUD screen, an unread-count
bell badge, a "latest notifications" dashboard card, and an optional real-time popover powered by
Mercure. Disabled by default; enable via:

```yaml
# config/packages/coop_tilleuls_ea_extensions.yaml
coop_tilleuls_ea_extensions:
    notifications:
        enabled: true
```

## Consumer app setup (one-time)

On top of the [shared setup](../README.md#setup-one-time-shared-by-every-feature) (Twig and JavaScript):

1. Import the bundle's notification routes, e.g. in `config/routes/ea_extensions.yaml`:
   ```yaml
   ea_extensions_notifications:
       resource: '@EAExtensionsBundle/src/Notifications/Controller/'
       type: attribute
   ```
   `NotificationCrudController`'s own routes are registered automatically once it's added to a
   Dashboard's menu (see step 2).
2. Add `NotificationCrudController` to your Dashboard's menu:
   ```php
   use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\EasyAdmin\NotificationCrudController;

   yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');
   ```
   It's a ready-to-use CRUD controller, but if you want more specific feature, write your own controller
   instead (reusing `NotificationRepository`, `NotificationManager`, `NotificationVoter`, and
   `NotificationTreatmentFilter` as plain autowired services).
3. Point Doctrine at your recipient entity — required before the next step, since
   `Notification::$recipient` is typed against an interface Doctrine can't map without it. See
   [Recipients](#recipients) below.
4. Run `doctrine:migrations:diff` after installing to generate the `ea_extensions_notification`
   and `ea_extensions_notification_treatment` tables.
5. Embed the bell/popover bar and the "latest notifications" card wherever you want them in your
   own layout:
   ```twig
   {{ include('@EAExtensions/notifications/components/notification_bar.html.twig') }}
   {{ render(controller('CoopTilleuls\\Bundle\\EAExtensionsBundle\\Notifications\\Controller\\LatestNotificationsController')) }}
   ```

## Recipients

A notification's `recipient` (and optional `sender`) is typed against
`CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface`,
not a concrete class — the bundle works regardless of how your app models recipients, while still
getting a real foreign key (JOINs, an indexed column, cascade delete on the recipient side). Make
your recipient entity implement
that interface, then point Doctrine at it with `resolve_target_entities`:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        resolve_target_entities:
            CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface: App\Entity\User
```

Sending code resolves and passes recipient entities itself (see "Sending a notification" below);
reading "my notifications" (index, unread count, latest-notifications card, and
[the access voter](#restricting-who-sees-what)) resolves every identity that counts as "mine"
through `NotificationRecipientResolverInterface::resolveCurrentRecipients()`, aliased by default
to `SecurityUserNotificationRecipientResolver` (the current Security user alone, when that user's
class itself implements `NotificationRecipientInterface`). Real-time updates use the separate,
single-identity `resolveCurrentRecipient()` instead (see
[Real-time updates](#real-time-updates-mercure)) — most apps only ever have one current identity,
so both methods return "the same thing, wrapped differently" by default.

If your app's Security user isn't itself the recipient entity (e.g. it's an in-memory user, an
API token identity, or the recipient is keyed by something else like an "active responsibility"),
both methods return nothing resolvable by default — "my notifications" reads then always come back
empty, with no error. Override the alias with your own implementation:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientInterface;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AppNotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    public function __construct(private readonly Security $security) {}

    public function resolveCurrentRecipient(): ?NotificationRecipientInterface
    {
        $user = $this->security->getUser();

        // map $user to your actual recipient entity here, e.g. a repository lookup
        return $user instanceof SomeIdentity ? $this->recipientRepository->findForIdentity($user) : null;
    }

    public function resolveCurrentRecipients(): iterable
    {
        // implement separately only when a single logged-in user can hold more than one valid
        // recipient identity at once (e.g. a currently active one plus historical ones) —
        // otherwise this is enough:
        $recipient = $this->resolveCurrentRecipient();

        return null !== $recipient ? [$recipient] : [];
    }
}
```

```yaml
# config/services.yaml
services:
    CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Recipient\NotificationRecipientResolverInterface:
        alias: App\Notifications\AppNotificationRecipientResolver
```

## Restricting who sees what

`NotificationVoter::IS_RELATED` delegates the actual decision to
`CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface`,
aliased by default to `DefaultNotificationRelationChecker` (grants access when the notification's
recipient is one of `resolveCurrentRecipients()`). Override that alias when your access rules go
beyond that — e.g. excluding some roles regardless of a recipient match:

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface;

final class AppNotificationRelationChecker implements NotificationRelationCheckerInterface
{
    public function isRelated(Notification $notification): bool
    {
        // your own rules here, e.g. excluding some roles regardless of a recipient match
    }
}
```

```yaml
# config/services.yaml
services:
    CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Security\NotificationRelationCheckerInterface:
        alias: App\Notifications\AppNotificationRelationChecker
```

## Sending a notification

```php
use CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Entity\Notification;

$notification = new Notification('validation', 'Demande de délégation à valider');
$notification->setContentKey('notifications.validation.some_key')
    ->setContentParams(['%name%' => $subject->getName()])
    ->setButtonLabel('Voir la fiche')
    ->setButtonUrl($url)
    ->addTreatment(); // optional: this notification requires an action

$notificationManager->send($notification, recipients: [$user1, $user2], flush: true);
```

`send()` clones the notification once per recipient. If a `NotificationTreatment` was attached
via `addTreatment()`, each clone gets its own treatment row unless you call
`$notification->shareTreatment()` first — in which case every recipient shares the same treatment,
so one recipient marking it done marks it done for everyone.

## Rich content

`component`/`componentParams` hold the FQCN and props of a Twig Component rendered on the detail
page (`displayInNewCard` controls whether it renders inline or in its own card) — this is the
extension point for attaching arbitrary rich content to a notification without the bundle needing
to know anything about it.

## Extending the detail page

The detail template exposes an empty `{% block sender %}{% endblock %}` where you can render
information about `notification.sender` by extending it:

```twig
{# templates/bundles/EAExtensionsBundle/notifications/crud/detail.html.twig #}
{% extends '@EAExtensions/notifications/crud/detail.html.twig' %}

{% block sender %}
    {# render your own "sender" card here, e.g. notification.sender.name #}
{% endblock %}
```

## Events

| Event | Dispatched | Use it to                                                                  |
|---|---|----------------------------------------------------------------------------|
| `BeforeNotificationPersistedEvent` | once per recipient clone, before it's persisted | trigger a notification per recipient                                       |
| `AfterNotificationPersistedEvent` | once per persisted clone, only when `send()` was called with `flush: true` | side effects tied to persistence (e.g. sending an email)                   |
| `BeforeNotificationPublishedEvent` | once per recipient clone, before it's handed to the publisher | mutate the Mercure payload, or call `stopPropagation()` to skip publishing |
| `NotificationMarkedAsReadEvent` | when a notification is marked read | react to a notification being read                                         |
| `NotificationTreatmentChangedEvent` | when a notification's treatment "pending" state is toggled from the CRUD | send a follow-up notification (e.g. back to the sender)                    |

## Real-time updates (Mercure)

Disabled by default. Enable it once `symfony/mercure` (and a configured Mercure hub) are set up:

```yaml
coop_tilleuls_ea_extensions:
    notifications:
        mercure:
            enabled: true
```

The topic a notification is published to comes from
`CoopTilleuls\Bundle\EAExtensionsBundle\Notifications\Topic\NotificationTopicResolverInterface`,
aliased by default to `DefaultNotificationTopicResolver` (`/notifications/{recipient.id}`).
Override that alias to use a different topic naming scheme. With Mercure disabled, the bundle
publishes nothing — `NotificationManager` always depends on
`NotificationPublisherInterface`, aliased to a no-op `NullNotificationPublisher` by default.

Notifications are published as **private** Mercure updates: only a subscriber presenting a JWT
whose subscribe authorization (`mercure.subscribe` claim in the legacy protocol,
`authorization_details` in Mercure 1.0) covers that exact topic receives it (the bundle's own
`notification_bar.html.twig` requests that claim for the current recipient's own topic when
rendering the popover). This means the hub's `jwt.secret` (`config/packages/mercure.yaml`) is
load-bearing for security, not just for publishing — without it, subscriber JWTs can't be minted
and the popover never receives anything, but skipping it is not a safe fallback.

Both Mercure protocols are supported. The popover subscribes with the query parameters of the
default hub's `protocol_version` (`symfony/mercure-bundle` ≥ 0.6): `match` / `last_event_id` for
`1.0`, `topic` / `lastEventID` for `0.x`. With `symfony/mercure` < 0.9 (no protocol version), the
legacy protocol is assumed.
