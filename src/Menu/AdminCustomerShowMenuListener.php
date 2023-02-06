<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Menu;

use Sylius\Bundle\AdminBundle\Event\CustomerShowMenuBuilderEvent;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminCustomerShowMenuListener
{
    public function addAdminCustomerShowMenuItems(CustomerShowMenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $customer = $event->getCustomer();

        if (null !== $customer->getUser()) {
            $menu
                ->addChild('impersonate', [
                    'route' => 'workouse_digital_wallet_credit_index',
                    'routeParameters' => ['customerId' => $customer->getId()],
                ])
                ->setAttribute('type', 'link')
                ->setLabel('workouse_digital_wallet.admin.menu.credits')
                ->setLabelAttribute('icon', 'unhide')
                ->setLabelAttribute('color', 'blue');
        }
    }
    public function addAccountMenuItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild('wallet', [
                'route' => '3attar_sylius_wallet_plugin_shop_account_index'
            ])
            ->setLabel('app.ui.credits')
            ->setLabelAttribute('icon', 'bullhorn')
        ;
    }
}
