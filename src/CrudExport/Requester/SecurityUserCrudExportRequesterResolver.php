<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Requester;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityUserCrudExportRequesterResolver implements CrudExportRequesterResolverInterface
{
    public function __construct(private Security $security)
    {
    }

    public function resolve(AdminContext $context): string
    {
        return $this->sanitize($context->getUser()?->getUserIdentifier());
    }

    public function resolveCurrent(): string
    {
        return $this->sanitize($this->security->getUser()?->getUserIdentifier());
    }

    private function sanitize(?string $identifier): string
    {
        if (null === $identifier || '' === $identifier) {
            throw new \RuntimeException('Unable to resolve the current user to identify a CrudExport requester.');
        }

        return preg_replace('/[^a-zA-Z0-9_.@-]/', '_', $identifier) ?? $identifier;
    }
}
