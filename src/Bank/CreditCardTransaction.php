<?php

namespace App\Bank;

use App\Bank\Exception\CcParseException;
use Fhp\Model\StatementOfAccount\Transaction;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class CreditCardTransaction extends BankTransaction
{
    /**
     * @var string
     */
    protected $ccIssuer;

    /**
     * @var string
     */
    protected $ccIdentifier;

    /**
     * @var string
     */
    protected $ccValue;

    /**
     * @var string
     */
    protected $ccCurrency;

    /**
     * @var string
     */
    protected $ccPostingLocation;

    /**
     * @var string
     */
    protected $ccPostingDate;

    /**
     * @var string
     */
    protected $ccExchangeRate;

    /**
     * @var string
     */
    protected $ccUnknownFloat;

    /**
     * @var string
     */
    protected $ccUnknownId;

    /**
     * @param Transaction $transaction
     * @throws CcParseException
     */
    public function __construct(Transaction $transaction = null)
    {
        parent::__construct($transaction);
        if ($transaction !== null) {
            $this->parseDescription();
        }
    }

    /**
     * @param string[] $structuredDescription
     * @return CreditCardTransaction
     * @throws CcParseException
     */
    public function setStructuredDescription($structuredDescription): self
    {
        parent::setStructuredDescription($structuredDescription);
        $this->parseDescription();
        return $this;
    }

    /**
     * @return string
     */
    public function getCcIssuer(): string
    {
        return $this->ccIssuer;
    }

    /**
     * @return string
     */
    public function getCcIdentifier(): string
    {
        return $this->ccIdentifier;
    }

    /**
     * @return string
     */
    public function getCcValue(): string
    {
        return $this->ccValue;
    }

    /**
     * @return string
     */
    public function getCcPostingLocation(): string
    {
        return $this->ccPostingLocation;
    }

    /**
     * @return string
     */
    public function getCcPostingDate(): \DateTime
    {
        return $this->ccPostingDate;
    }

    /**
     * @return string
     */
    public function getCcExchangeRate(): string
    {
        return $this->ccExchangeRate;
    }

    /**
     * @return string
     */
    public function getCcUnknownFloat(): string
    {
        return $this->ccUnknownFloat;
    }

    /**
     * @return string
     */
    public function getCcUnknownId(): string
    {
        return $this->ccUnknownId;
    }

    /**
     * @return string
     */
    public function getCcCurrency(): string
    {
        return $this->ccCurrency;
    }

    public function getMainDescription(): string
    {
        return
            'Location: ' . ucwords(strtolower($this->getCcPostingLocation())) . ', ' .
            'CC-Id: ' . ucwords(strtolower($this->getCcIssuer())) . '-' . preg_replace('/(\d{1,4})$/', '-\1',
                $this->getCcIdentifier()) . ', ' .
            'Value: ' . $this->getCcValue() . ' ' . $this->getCcCurrency() . ', ' .
            (!empty($this->getCcExchangeRate()) ? 'Exchange-Rate: ' . $this->getCcExchangeRate() . ', ' : '') .
            'UnknownFloat: ' . number_format($this->getCcUnknownFloat(), 2, ',', '') . ', ' .
            'UnknownId: ' . $this->getCcUnknownId() . ', ' .
            '';
    }

    public function getDescription(): string
    {
        return $this->getMainDescription();
    }

    /**
     * @throws CcParseException
     */
    private function parseDescription(): void
    {
        if (preg_match('/^\s*' .
            '(?P<issuer>[A-Z]{2,4})' .
            '(?P<ccnumber>[0-9]{8})' .
            '(?P<location>[A-Z\s\.0-9\-]*[A-Z\.\-\s])' .
            '(?P<value>[0-9]+,[0-9]{2})(?P<currency>[A-Z]{2,5})' .
            '(?P<exchangerate>[0-9]+,[0-9]+)\s*' .
            '(?P<date>[0-9]\s*[0-9]\s*\.\s*[0-9]\s*[0-9]\s*\.)\s*' .
            '(?P<unknownfloat>[0-9]+,[0-9]{2})\s*' .
            '(?P<unknownid>[0-9]+)\s*$/', $this->structuredDescription['SVWZ'], $matches)) {
            $this->ccIssuer = $matches['issuer'];
            $this->ccIdentifier = $matches['ccnumber'];
            $this->ccPostingLocation = trim($matches['location']);
            $this->ccValue = (float)str_replace(',', '.', $matches['value']);
            $this->ccCurrency = $matches['currency'];
            $this->ccExchangeRate = (float)str_replace(',', '.', $matches['exchangerate']);
            $this->ccPostingDate = \DateTime::createFromFormat('d.m.Y H:i:s',
                str_replace(' ', '', $matches['date']) .
                ($this->getBookingDate() ?? new \DateTime())->format('Y') . ' 00:00:00');
            $this->ccUnknownFloat = (float)str_replace(',', '.', $matches['unknownfloat']);
            $this->ccUnknownId = $matches['unknownid'];
        } else {
            throw new CcParseException('Could not parse CC-data');
        }
    }
}