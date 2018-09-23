<?php

namespace App\Bank;

use App\Bank\Exception\CcParseException;
use Fhp\FinTs;
use Fhp\Model\StatementOfAccount\Statement;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class RetrieveTransactionService
{
    /**
     * @var FinTs
     */
    protected $finTs;

    /**
     * RetrieveTransactionService constructor.
     *
     * @param FinTs $finTs
     */
    public function __construct(FinTs $finTs)
    {
        $this->finTs = $finTs;
    }

    /**
     * @param FinTs $finTs
     */
    public function setFinTs(FinTs $finTs): void
    {
        $this->finTs = $finTs;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array|Statement[]
     * @throws \Fhp\Adapter\Exception\AdapterException
     * @throws \Fhp\Adapter\Exception\CurlException
     * @throws \Exception
     */
    public function getStatements(\DateTime $from, \DateTime $to): array
    {
        $accounts = $this->finTs->getSEPAAccounts();
        $oneAccount = $accounts[0];
        $soa = $this->finTs->getStatementOfAccount($oneAccount, $from, $to);
        if ($soa === null) {
            throw new \RuntimeException('Could not get bank statements');
        }
        return $soa->getStatements();
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array|BankTransaction[]
     * @throws \Fhp\Adapter\Exception\AdapterException
     * @throws \Fhp\Adapter\Exception\CurlException
     * @throws \Exception
     */
    public function getTransactions(\DateTime $from, \DateTime $to): array
    {
        $statements = $this->getStatements($from, $to);
        $transactions = [];
        $compare = clone $to;
        $compare->add(new \DateInterval('P1D'));
        foreach ($statements as $statement) {
            foreach ($statement->getTransactions() as $transaction) {

                if ($transaction->getValutaDate() > $compare && $transaction->getBookingDate() > $compare) {
                    continue;
                }

                if ($transaction->getCreditDebit() === 'debit') {
                    $transaction->setAmount(-1 * $transaction->getAmount());
                }

                try {
                    $transaction = new CreditCardTransaction($transaction);
                } catch (CcParseException $exception) {
                    $transaction = new BankTransaction($transaction);
                }
                $transactions[] = $transaction;
            }
        }
        return $transactions;
    }
}
