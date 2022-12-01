<?php

namespace Workouse\SyliusDigitalWalletPlugin\EventListener;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webmozart\Assert\Assert;
use Workouse\SyliusDigitalWalletPlugin\Service\WalletService;

class OrderCompletedListener
{
    private $walletService;
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function detractBalance(GenericEvent $event):void
    {
        $this->walletService->detractBalance($event->getSubject());
    }

}
