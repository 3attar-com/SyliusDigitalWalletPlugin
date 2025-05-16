<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Service;
use App\Entity\Customer\Customer;
use App\Entity\Product\Product;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Order\Factory\AdjustmentFactory;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Order\Model\AdjustmentInterface as OrderAdjustmentInterface;
use Sylius\Component\Order\Model\Order;
use Sylius\Component\Order\Model\OrderItem;
use Sylius\Component\Order\Processor\CompositeOrderProcessor;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\ShopApiPlugin\ViewRepository\Cart\CartViewRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Workouse\SyliusDigitalWalletPlugin\Entity\Credit;
use Workouse\SyliusDigitalWalletPlugin\Entity\CreditInterface;
use Sylius\Bundle\ShopBundle\Calculator\OrderItemsSubtotalCalculatorInterface;
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

    /** @var CartViewRepositoryInterface */
    private $cartQuery;

    /** @var ViewHandlerInterface */
    private $viewHandler;

    private $calculator;

    private $logger;
    private $localeContext;

    private $taxonCreditId;
    public function __construct(
        Security $security,
        EntityManager $entityManager,
        CurrencyConverterInterface $currencyConverter,
        CurrencyContextInterface $currencyContext,
        AdjustmentFactory $adjustmentFactory,
        CompositeOrderProcessor $orderProcessor,
        CartViewRepositoryInterface $cartQuery,
        ViewHandlerInterface $viewHandler,
        OrderItemsSubtotalCalculatorInterface $calculator,
        LoggerInterface $logger,
        $taxonCreditId,
        LocaleContextInterface $localeContext
    ) {
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->currencyConverter = $currencyConverter;
        $this->currencyContext = $currencyContext;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->orderProcessor = $orderProcessor;
        $this->cartQuery = $cartQuery;
        $this->viewHandler = $viewHandler;
        $this->calculator = $calculator;
        $this->logger = $logger;
        $this->taxonCreditId = $taxonCreditId;
        $this->localeContext = $localeContext;
    }

    public function balance($customer = null)
    {
        /** @var ShopUserInterface $user */
        $user = $this->security->getUser();

        return array_sum(array_map(function (Credit $credit) {
                return $credit->getexpiredAt() > new \DateTime('@'.strtotime('now')) || $credit->getexpiredAt() == null?
                    $this->currencyConverter->convert($credit->getAmount(), $credit->getCurrencyCode(), $this->currencyContext->getCurrencyCode())
                    : 0;
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
            $adjustment = $adjustment / 100;
            $this->logger->info("Wallet used for order #[{$order->getId()}] — amount deducted: SAR { $adjustment }");
        }
    }

    public function useWallet(Order $order , $discountAmount)
    {
        $this->removeWallet($order);
        $orderTotal = $order->getTotal();
        $adjustment = $this->createAdjustment();
        $discountAmount = $discountAmount * 100;


        $adjustment->setType(CreditInterface::TYPE);
        if ($orderTotal > $discountAmount)  {
            $adjustment->setAmount(-$discountAmount );
        }   else    {
            $adjustment->setAmount(-$orderTotal);
        }
        $adjustment->setNeutral(false);
        $adjustment->setLabel('Wallet Order  Adjustment');
        $order->addAdjustment($adjustment);
        $this->entityManager->flush();
        return (int)($orderTotal > $discountAmount ? -$discountAmount : -$orderTotal);

    }
    private function createAdjustment(
    ): OrderAdjustmentInterface {

        $adjustment = $this->adjustmentFactory->createNew();
        $adjustment->setLabel('wallet');

        return $adjustment;
    }
    public function removeWallet(Order $order)
    {

        $order->removeAdjustmentsRecursively(CreditInterface::TYPE);

        $order->recalculateAdjustmentsTotal();
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

    public function getCart($token , $amount , $order) :Response
    {
       $response =  $this->viewHandler->handle(
            View::create(
                $this->cartQuery->getOneByToken($token),
                Response::HTTP_OK
            )
        );
        $total = $this->calculator->getSubtotal($order);
        $response = json_decode($response->getContent(), true);
        $response['totals']['wallet_used'] = $amount ;
        $response['totals']['items'] = $total ;
        dd($order);
        return new Response(json_encode($response));
    }

    public function addCreditToCustomer($customer , $note , $data)
    {
        $date = \DateTime::createFromFormat('d/m/Y',$data['expiredAt']);

        $credit = new Credit();
        $credit->setCustomer($customer);
        $credit->setAmount($data['wallet']);
        $credit->setAction($note);
        $credit->setExpiredAt($date);
        $credit->setUpdatedAt($date);
        $credit->setCurrencyCode($this->currencyContext->getCurrencyCode());
        $this->entityManager->persist($credit);
        $this->entityManager->flush();
    }

    public function getProducts()
    {
        return $this->entityManager->getRepository(Product::class)->findByTaxon($this->taxonCreditId, $this->localeContext->getLocaleCode());
    }
}
