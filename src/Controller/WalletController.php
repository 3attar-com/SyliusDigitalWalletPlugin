<?php


namespace Workouse\DigitalWalletPlugin\Controller;

use Sylius\Component\Order\Context\CompositeCartContext;
use Sylius\Component\Order\Model\Order;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Workouse\DigitalWalletPlugin\Entity\Credit;
use Workouse\DigitalWalletPlugin\Form\Type\CreditType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Workouse\DigitalWalletPlugin\Service\WalletService;

class WalletController extends AbstractController
{
    public function indexAction($customerId): Response
    {
        $customer = $this->container->get('sylius.repository.customer')->findOneBy([
            'id' => $customerId
        ]);

        if (!$customer) {
            throw $this->createNotFoundException('Not found user');
        }

        $credits = $this->getDoctrine()->getRepository(Credit::class)->findBy([
            'customer' => $customer
        ]);

        return $this->render('@WorkouseDigitalWalletPlugin/admin/index.html.twig', [
            'credits' => $credits,
            'customer' => $customer
        ]);
    }

    public function newAction($customerId, Request $request): Response
    {

        $customer = $this->container->get('sylius.repository.customer')->findOneBy([
            'id' => $customerId
        ]);

        if (!$customer) {
            throw $this->createNotFoundException('Not found user');
        }

        $credit = new Credit();

        $form = $this->createForm(CreditType::class, $credit);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $credit = $form->getData();
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

        return $this->render('@WorkouseDigitalWalletPlugin/admin/new.html.twig', [
            'form' => $form->createView(),
            'customer' => $customer
        ]);
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

        $walletService->useWallet($order);

        /** @var SessionInterface $session */
        $session = $request->getSession();

        /** @var FlashBagInterface $flashBag */
        $flashBag = $session->getBag('flashes');
        $flashBag->add('success', 'workouse_digital_wallet.balance_used');

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
}
