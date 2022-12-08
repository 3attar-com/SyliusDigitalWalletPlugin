<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Controller;

use FOS\RestBundle\View\View;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Order\Context\CompositeCartContext;
use Sylius\Component\Order\Model\Order;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Workouse\SyliusDigitalWalletPlugin\Entity\Credit;
use Workouse\SyliusDigitalWalletPlugin\Form\Type\CreditType;
use Workouse\SyliusDigitalWalletPlugin\Service\WalletService;
use Webmozart\Assert\Assert;

class WalletController extends AbstractController
{
    public function showAction($email): Response
    {
        $walletService = $this->get('workouse_digital_wallet.wallet_service');
        $credit = $walletService->balanceByEmail($email);
        return new JsonResponse([
            'credit' => $credit / 100
        ]);;
    }

    public function indexAction($customerId): Response
    {
        $customer = $this->container->get('sylius.repository.customer')->findOneBy([
            'id' => $customerId,
        ]);

        if (!$customer) {
            throw $this->createNotFoundException('Not found user');
        }

        $credits = $this->getDoctrine()->getRepository(Credit::class)->findBy([
            'customer' => $customer,
        ]);

        return $this->render('@WorkouseSyliusDigitalWalletPlugin/admin/index.html.twig', [
            'credits' => $credits,
            'customer' => $customer,
        ]);
    }

    public function newAction($customerId, Request $request): Response
    {
        $customer = $this->container->get('sylius.repository.customer')->findOneBy([
            'id' => $customerId,
        ]);

        if (!$customer) {
            throw $this->createNotFoundException('Not found user');
        }

        $credit = new Credit();

        $form = $this->createForm(CreditType::class, $credit);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $credit = $form->getData();
            $credit->setAmount($credit->getAmount() * 100);
            $credit->setCustomer($customer);

            $em = $this->getDoctrine()->getManager();
            $em->persist($credit);
            $em->flush();

            /** @var SessionInterface $session */
            $session = $request->getSession();

            /** @var FlashBagInterface $flashBag */
            $flashBag = $session->getBag('flashes');
            $flashBag->add('success', 'workouse_digital_wallet.credit_added');

            return $this->redirectToRoute('workouse_digital_wallet_credit_index', ['customerId' => $customerId]);
        }

        return $this->render('@WorkouseSyliusDigitalWalletPlugin/admin/new.html.twig', [
            'form' => $form->createView(),
            'customer' => $customer,
        ]);
    }

    public function useCreditAction(Request $request): Response
    {
        $walletService = $this->get('workouse_digital_wallet.wallet_service');

        Assert::notNull($request->get('email'), "Email is Null ");
        Assert::notNull($request->get('amount'), "Amount is Null ");
        Assert::notNull($request->get('token'), "Token is Null ");
        $balance = $walletService->balanceByEmail($request->get('email')) / 100;
        Assert::greaterThanEq($balance, $request->get('amount'), "credit must be less than or equal to your balance");

        $orderRepository = $this->container->get('sylius.repository.order');
        $order = $orderRepository->findCartByTokenValue($request->get('token'));
        $amount = $walletService->useWallet($order, $request->get('amount'));

        $response = $walletService->getCart($request->get('token') , $amount);

        return $response;
    }

    public function useAction(Request $request)
    {
        $orderRepository = $this->container->get('sylius.repository.order');
        /** @var CompositeCartContext $compositeCartContext */
        $compositeCartContext = $this->get('sylius.context.cart');
        $orderId = $compositeCartContext->getCart()->getId();
        /** @var Order $order */
        $order = $orderRepository->findCartById($orderId);

        /** @var WalletService $walletService */
        $walletService = $this->get('workouse_digital_wallet.wallet_service');

        /** @var SessionInterface $session */
        $session = $request->getSession();

        /** @var CurrencyContextInterface $currencyContext */
        $currencyContext = $this->get('sylius.context.currency');
        $currencyCode = $currencyContext->getCurrencyCode();

        /** @var LocaleContextInterface $localeContext */
        $localeContext = $this->get('sylius.context.locale');
        $localeCode = $localeContext->getLocaleCode();

        /** @var MoneyFormatterInterface $moneyFormatter */
        $moneyFormatter = $this->get('sylius.money_formatter');

        /** @var TranslatorInterface $translator */
        $translator = $this->get('translator');

        /** @var FlashBagInterface $flashBag */
        $flashBag = $session->getBag('flashes');
        $flashBag->add('success', $translator->trans('workouse_digital_wallet.balance_used', ['amount' => $moneyFormatter->format($walletService->useWallet($order, $request->get('amount')), $currencyCode, $localeCode)], 'flashes'));

        return new RedirectResponse($this->generateUrl('sylius_shop_cart_summary'));
    }

    public function removeAction(Request $request)
    {
        $orderRepository = $this->container->get('sylius.repository.order');
        /** @var CompositeCartContext $compositeCartContext */
        $compositeCartContext = $this->get('sylius.context.cart');
        $orderId = $compositeCartContext->getCart()->getId();
        /** @var Order $order */
        $order = $orderRepository->findCartById($orderId);

        /** @var WalletService $walletService */
        $walletService = $this->get('workouse_digital_wallet.wallet_service');

        $walletService->removeWallet($order);

        /** @var SessionInterface $session */
        $session = $request->getSession();

        /** @var FlashBagInterface $flashBag */
        $flashBag = $session->getBag('flashes');
        $flashBag->add('success', 'workouse_digital_wallet.balance_removed');

        return new RedirectResponse($this->generateUrl('sylius_shop_cart_summary'));
    }

    public function refundAction(Request $request)
    {
        $orderRepository = $this->container->get('sylius.repository.order');
        $order = $orderRepository->findOneBy(['id' => $request->get('orderId')]);
        /** @var WalletService $walletService */
        $walletService = $this->get('workouse_digital_wallet.wallet_service');
        $walletService->refundWallet($order);

        return new RedirectResponse($this->generateUrl('sylius_admin_order_show' , ['id'=>$order->getId()]));
    }

    public function removeCreditAction(Request $request):Response
    {
        $walletService = $this->get('workouse_digital_wallet.wallet_service');

        Assert::notNull($request->get('email'), "Email is Null ");
        Assert::notNull($request->get('token'), "Token is Null ");
        $orderRepository = $this->container->get('sylius.repository.order');
        $order = $orderRepository->findCartByTokenValue($request->get('token'));
        $walletService->removeWallet($order);
        return new JsonResponse([
            'success' => true
        ]);
    }
}
