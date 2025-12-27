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
        $isAgent = false;
        $isUser = false;

        try {
            $isAdmin = (bool) $this->authChecker->isGranted('ROLE_ADMIN');
            $isAgent = (bool) $this->authChecker->isGranted('ROLE_AGENT');
            $isUser = (bool) $this->authChecker->isGranted('ROLE_USER');
        } catch (\Exception $e) {
            // no token / not authenticated -> keep false
        }

        return [
            'is_admin' => $isAdmin,
            'is_agent' => $isAgent,
            'is_user' => $isUser,
        ];
    }
}
