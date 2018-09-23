<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PayPalTransactionRepository")
 */
class PayPalTransaction implements YnabCompatibleTransaction
{
    public const TRANSACTION_TYPE_PAYMENT = 'PAYMENT';

    public const TRANSACTION_TYPE_TRANSFER = 'TRANSFER_BANK';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $transactionId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $transactionReferenceId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $checksum;

    public function getId()
    {
        return $this->id;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId($transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @return mixed
     */
    public function getTransactionReferenceId()
    {
        return $this->transactionReferenceId;
    }

    /**
     * @param mixed $transactionReferenceId
     */
    public function setTransactionReferenceId($transactionReferenceId): void
    {
        $this->transactionReferenceId = $transactionReferenceId;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): void
    {
        $this->date = $date;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getPayer(): string
    {
        return $this->payer;
    }

    public function setPayer(string $payer): void
    {
        $this->payer = $payer;
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
