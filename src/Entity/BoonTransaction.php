<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 * @ORM\Entity(repositoryClass="App\Repository\BoonTransactionRepository")
 */
class BoonTransaction implements YnabCompatibleTransaction
{
    public const TRANSACTION_TYPE_USAGE = 'CARD_USAGE';
    public const TRANSACTION_TYPE_REFILL = 'CREDIT_CARD_TOPUP';
    public const TRANSACTION_TYPE_COUPON = 'COUPON';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $currency;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $payer;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $checksum;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     */
    public function setCurrency($currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getPayer(): string
    {
        return $this->payer;
    }

    /**
     * @param mixed $payer
     */
    public function setPayer($payer): void
    {
        $this->payer = $payer;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date): void
    {
        $this->date = $date;
    }



    public function getChecksum(): string
    {
        if ($this->checksum === null) {
            $this->calculateChecksum();
        }
        return $this->checksum;
    }

    public function calculateChecksum(): void
    {
        /** @noinspection PhpUndefinedClassInspection */
        $cVars = array_keys(get_class_vars(__CLASS__));
        $oVars = get_object_vars($this);
        $chechsumArray = [];
        foreach ($cVars as $var) {
            $chechsumArray[$var] = $oVars[$var];
        }
        $this->checksum = hash('sha256', json_encode($chechsumArray));
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}
