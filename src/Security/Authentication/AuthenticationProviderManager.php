<?php

declare(strict_types=1);

namespace App\Security\Authentication;

use App\Security\AccessBlockService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager as SymfonyManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * There is no event we could listen to that is triggered after the credentials
 * were extracted from the request and before the UserProviders are called.
 * So we have to hack into the process by decorating this ProviderManager,
 * which is called by the UsernamePasswordJsonAuthenticationListener with the
 * credentials, to check access before the database is queried for the user.
 *
 * @todo use the new authenticator system https://symfony.com/doc/current/security/experimental_authenticators.html
 */
class AuthenticationProviderManager implements AuthenticationManagerInterface
{
    private AccessBlockService $accessBlock;
    protected SymfonyManager $decorated;

    public function __construct(SymfonyManager $decorated, AccessBlockService $accessBlock)
    {
        $this->decorated = $decorated;
        $this->accessBlock = $accessBlock;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if ($token instanceof UsernamePasswordToken
            && !$this->accessBlock->loginAllowed($token->getUsername())
        ) {
            throw new AccessDeniedHttpException("Access blocked, to many requests.");
        }

        return $this->decorated->authenticate($token);
    }
}
