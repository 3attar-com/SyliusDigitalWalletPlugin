<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Form\Type;

use Doctrine\DBAL\Types\DateType;
use Sylius\Component\Currency\Model\Currency;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('amount', TextType::class, [
                'label' => 'workouse_digital_wallet.admin.form.amount',
                'constraints' => [
                    new NotBlank([
                        'message' => 'workouse_digital_wallet.amount.not_blank',
                    ]),
                    new Positive([
                        'message' => 'workouse_digital_wallet.amount.positive',
                    ]),
                ],
            ])
            ->add('currencyCode', EntityType::class, [
                'label' => 'workouse_digital_wallet.admin.form.currency_code',
                'class' => Currency::class,
                'choice_label' => 'code',
                'constraints' => [
                    new NotBlank([
                        'message' => 'workouse_digital_wallet.currency_code.not_blank',
                    ]),
                ],
            ])->add('expiredAt', DateTimeType::class, [
                'input' => 'datetime_immutable',
                'label' => 'Expiration Date',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => true,
            ])
            ->add('action', TextType::class, [
                'label' => 'workouse_digital_wallet.admin.form.action',
                'constraints' => [
                    new NotBlank([
                        'message' => 'workouse_digital_wallet.action.not_blank',
                    ]),
                ],
            ]);
    }
}
