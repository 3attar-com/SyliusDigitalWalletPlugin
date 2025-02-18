<?php

declare(strict_types=1);

namespace Workouse\SyliusDigitalWalletPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Resource\Model\TimestampableTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="workouse_digital_wallet_credit")
 */
class Credit implements CreditInterface
{
    use TimestampableTrait;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP", "on update": "CURRENT_TIMESTAMP"})
     */
    protected $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\ManyToOne("Sylius\Component\Customer\Model\CustomerInterface")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     */
    protected $customer;

    /**
     * @ORM\Column(type="integer")
     */
    protected $amount;

    /**
     * @ORM\Column(type="string")
     */
    protected $currencyCode;

    /**
     * @ORM\Column(type="string")
     */
    protected $action;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $expiredAt;

    public function getId()
    {
        return $this->id;
    }

    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCustomer($customer): void
    {
        $this->customer = $customer;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode($currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action): void
    {
        $this->action = $action;
    }
    public function getExpiredAt()
    {
        return $this->expiredAt;
    }

    public function setExpiredAt($expiredAt)
    {
        return $this->expiredAt = $expiredAt;
    }

}
