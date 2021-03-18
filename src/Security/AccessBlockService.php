<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ActionLog;
use App\Repository\ActionLogRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AccessBlockService
{
    private ActionLogRepository $repository;
    private ParameterBagInterface $parameterBag;
    private RequestStack $requestStack;
    private array $settings;

    public function __construct(
        ActionLogRepository $repository,
        ParameterBagInterface $parameterBag,
        RequestStack $requestStack,
        array $settings
    ) {
        $this->repository = $repository;
        $this->parameterBag = $parameterBag;
        $this->requestStack = $requestStack;
        $this->settings = $settings;
    }

    public function loginAllowed(?string $username)
    {
        $config = $this->settings['access_block']['login'];

        return $this->checkAccess(
            [ActionLog::FAILED_LOGIN],
            $config['interval'],
            $config['limit'],
            $username
        );
    }

    public function passwordResetAllowed(?string $username)
    {
        $config = $this->settings['access_block']['password_reset'];

        return $this->checkAccess(
            [
                ActionLog::FAILED_PW_RESET_REQUEST,
                ActionLog::SUCCESSFUL_PW_RESET_REQUEST,
            ],
            $config['interval'],
            $config['limit'],
            $username
        );
    }

    public function validationConfirmAllowed(?string $username)
    {
        $config = $this->settings['access_block']['validation_confirm'];

        return $this->checkAccess(
            [ActionLog::FAILED_VALIDATION],
            $config['interval'],
            $config['limit'],
            $username
        );
    }

    public function checkAccess(array $actions, string $interval, int $limit, ?string $username): bool
    {
        if ($username &&
            !$this->checkUserActionLimit($username, $actions, $interval, $limit)
        ) {
            return false;
        }

        return $this->checkIpActionLimit($actions, $interval, $limit);
    }

    public function checkIpActionLimit(array $actions, string $interval, int $limit): bool
    {
        $ip = $this->getCurrentIp();
        if (!$ip) {
            return true;
        }

        $count = $this->repository->getActionCountByIp($ip, $actions, $interval);

        return $count < $limit;
    }

    public function checkUserActionLimit(string $username, array $actions, string $interval, int $limit): bool
    {
        $count = $this->repository
            ->getActionCountByUsername($username, $actions, $interval);

        return $count < $limit;
    }

    protected function getCurrentIp()
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? $request->getClientIp() : null;
    }
}
