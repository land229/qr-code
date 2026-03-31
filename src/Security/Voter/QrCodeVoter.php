<?php

namespace App\Security\Voter;

use App\Entity\QrCode;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class QrCodeVoter extends Voter
{
    const EDIT = 'edit';
    const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW])
            && $subject instanceof QrCode;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var QrCode $qrCode */
        $qrCode = $subject;

        // Les admins ont accès à tout
        if (in_array('ROLE_ADMIN', $token->getRoleNames())) {
            return true;
        }

        return match ($attribute) {
            self::EDIT, self::VIEW => $qrCode->getUser() === $user,
            default => false,
        };
    }
}