<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Service;
use App\Entity\Customer\Customer;
use Doctrine\ORM\EntityManager;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Order\Factory\AdjustmentFactory;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\Order;
use Sylius\Component\Order\Model\OrderItem;
use Sylius\Component\Order\Processor\CompositeOrderProcessor;
use Symfony\Component\Security\Core\Security;
use Workouse\SyliusDigitalWalletPlugin\Entity\Credit;
use Workouse\SyliusDigitalWalletPlugin\Entity\CreditInterface;
class WalletService
{
    /** @var Security */
    private $security;

    /** @var EntityManager */
    private $entityManager;

    /** @var CurrencyConverterInterface */
    private $currencyConverter;

    /** @var CurrencyContextInterface */
    private $currencyContext;

    /** @var AdjustmentFactory */
    private $adjustmentFactory;

    /** @var CompositeOrderProcessor */
    private $orderProcessor;

    public function __construct(
        Security $security,
        EntityManager $entityManager,
        CurrencyConverterInterface $currencyConverter,
        CurrencyContextInterface $currencyContext,
        AdjustmentFactory $adjustmentFactory,
        CompositeOrderProcessor $orderProcessor
    ) {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->currencyConverter = $currencyConverter;
        $this->currencyContext = $currencyContext;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->orderProcessor = $orderProcessor;
    }

    public function balance($customer = null)
    {
        /** @var ShopUserInterface $user */
        $user = $this->security->getUser();

        return array_sum(array_map(function (Credit $credit) {
            return $this->currencyConverter->convert($credit->getAmount(), $credit->getCurrencyCode(), $this->currencyContext->getCurrencyCode());
        }, $this->entityManager->getRepository(Credit::class)->findBy([
            'customer' => $customer ? $customer : $user->getCustomer(),
        ])));
    }

    public function balanceByEmail($email)
    {
        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => $email]);
        return $this->balance($customer);
    }

    public function detractBalance(OrderInterface $order)
    {
        $adjustment = array_sum(array_map(function (OrderItem $orderItem) {
                return array_sum(array_map(function (Adjustment $adjustment) {
                    if ($adjustment->getType() === CreditInterface::TYPE) {
                        return $adjustment->getAmount();
                    }
                }, $orderItem->getAdjustments()->toArray()));
            }, $order->getItems()->toArray())
        );

        if ($adjustment < 0) {
            /** @var ShopUserInterface $user */
            $user = $order->getUser();

            $credit = new Credit();
            $credit->setCustomer($user->getCustomer());
            $credit->setAmount($adjustment);
            $credit->setAction(CreditInterface::BUY);
            $credit->setCurrencyCode($this->currencyContext->getCurrencyCode());
            $this->entityManager->persist($credit);
            $this->orderProcessor->process($order);
            $this->entityManager->flush();
        }
    }

    public function useWallet(Order $order , $discountAmount)
    {
        $this->removeWallet($order);
        $discountAmount *= 100;
        $tot = 0;
        foreach ($order->getItems()->toArray() as $orderItem){
            $adjustment = $this->adjustmentFactory->createNew();
            $adjustment->setType(CreditInterface::TYPE);

            if ($discountAmount > $orderItem->getTotal()){
                $amount = -1 *  (($orderItem->getTotal())) ;
                $tot +=$amount;
                $discountAmount -= $orderItem->getTotal();
                $adjustment->setAmount( $amount);
                $adjustment->setLabel('Wallet');
                $orderItem->addAdjustment($adjustment);
            }
            else{
                $amount = -1 * ($discountAmount) ;
                $tot +=$amount;
                $adjustment->setAmount( $amount);
                $adjustment->setLabel('Wallet');
                $orderItem->addAdjustment($adjustment);
                $discountAmount = 0;
            }
            if ($discountAmount <= 0){
                break;
            }

        }
        $this->orderProcessor->process($order);
        $this->entityManager->flush();

        return (int)($tot*-1);
    }

    public function removeWallet(Order $order)
    {
        array_map(function (OrderItem $orderItem) {
            array_map(function (Adjustment $adjustment) use ($orderItem) {
                if ($adjustment->getType() === CreditInterface::TYPE) {
                    $orderItem->removeAdjustment($adjustment);
                }
            }, $orderItem->getAdjustments()->toArray());
        }, $order->getItems()->toArray());
        $this->orderProcessor->process($order);
        $this->entityManager->flush();
    }

    public function refundWallet(OrderInterface $order)
    {
        $adjustment = array_sum(array_map(function (OrderItem $orderItem) {
                return array_sum(array_map(function (Adjustment $adjustment) {
                    if ($adjustment->getType() === CreditInterface::TYPE) {
                        return $adjustment->getAmount();
                    }
                }, $orderItem->getAdjustments()->toArray()));
            }, $order->getItems()->toArray())
        );
        if ($adjustment < 0) {

            $this->removeWallet($order);

            /** @var ShopUserInterface $user */
            $user = $order->getUser();

            $credit = new Credit();
            $credit->setCustomer($user->getCustomer());
            $credit->setAmount($adjustment * -1);
            $credit->setAction(CreditInterface::BUY);
            $credit->setCurrencyCode($this->currencyContext->getCurrencyCode());
            $this->entityManager->persist($credit);
            $this->orderProcessor->process($order);
            $this->entityManager->flush();
        }
    }

}
