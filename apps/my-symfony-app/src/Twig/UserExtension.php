<?php

namespace App\Twig;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class UserExtension extends AbstractExtension implements GlobalsInterface
{
    private $authChecker;

    public function __construct(AuthorizationCheckerInterface $authChecker)
    {
        $this->authChecker = $authChecker;
    }

    public function getGlobals(): array
    {
        $isAdmin = false;

        try {
            $isAdmin = (bool) $this->authChecker->isGranted('ROLE_ADMIN');
        } catch (\Exception $e) {
            // no token / not authenticated -> keep false
        }

        return [
            'is_admin' => $isAdmin,
        ];
    }
}
