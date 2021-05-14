<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Security\AccessBlockService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class AccessBlockedVoter extends Voter
{
    public const PW_RESET = 'PW_RESET';
    public const REPORT_PROJECT = 'REPORT_PROJECT';
    public const VALIDATION_CONFIRM = 'VALIDATION_CONFIRM';

    private AccessBlockService $accessBlock;
    private Security $security;

    public function __construct(AccessBlockService $accessBlock, Security $security)
    {
        $this->accessBlock = $accessBlock;
        $this->security = $security;
    }

    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, [
                self::PW_RESET,
                self::REPORT_PROJECT,
                self::VALIDATION_CONFIRM,
            ]) && (is_string($subject) || null === $subject);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $attribute
     * @param string $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        if (self::PW_RESET === $attribute) {
            return $this->accessBlock->passwordResetAllowed($subject);
        }

        $user = $this->security->getUser();

        if (self::VALIDATION_CONFIRM === $attribute) {
            return $this->accessBlock->validationConfirmAllowed(
                $user ? $user->getUsername() : null
            );
        }

        if (self::REPORT_PROJECT === $attribute) {
            return $this->accessBlock->reportProjectAllowed(
                $user ? $user->getUsername() : null
            );
        }

        return true;
    }
}
