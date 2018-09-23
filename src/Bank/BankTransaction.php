<?php

namespace App\Bank;

use App\Entity\YnabCompatibleTransaction;
use Fhp\Model\StatementOfAccount\Transaction;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class BankTransaction extends Transaction implements YnabCompatibleTransaction
{
    /**
     * @param Transaction $transaction
     */
    public function __construct(Transaction $transaction = null)
    {
        if ($transaction !== null) {
            $this->setBookingDate($transaction->getBookingDate());
            $this->setValutaDate($transaction->getValutaDate());
            $this->setAmount(round($transaction->getAmount(),2));
            $this->setCreditDebit($transaction->getCreditDebit());
            $this->setBookingText($transaction->getBookingText());
            $this->setDescription1($transaction->getDescription1());
            $this->setDescription2($transaction->getDescription2());
            $this->setStructuredDescription($transaction->getStructuredDescription());
            $this->setBankCode($transaction->getBankCode());
            $this->setAccountNumber($transaction->getAccountNumber());
            $this->setName($transaction->getName());
        }
    }

    /** @noinspection OverridingDeprecatedMethodInspection */
    /**
     * @return \DateTimeInterface|null
     */
    public function getDate(): \DateTimeInterface
    {
        return parent::getBookingDate();
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return parent::getAmount();
    }

    public function getDescription(): string
    {
        return $this->getDescription1().' '.$this->getDescription2();
    }

    public function getPayer(): string
    {
        return $this->getName();
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }
}