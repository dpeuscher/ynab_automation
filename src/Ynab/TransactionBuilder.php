<?php

namespace App\Ynab;

use App\Bank\BankTransaction;
use App\Bank\CreditCardTransaction;
use App\Entity\PayPalTransaction;
use App\Entity\YnabCompatibleTransaction;
use Fhp\Model\StatementOfAccount\Transaction;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YNAB\Client\TransactionsApi;
use YNAB\Model\Account;
use YNAB\Model\SaveTransaction;
use YNAB\Model\SaveTransactionWrapper;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class TransactionBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var TransactionsApi
     */
    protected $transactionsApi;

    /**
     * TransactionBuilder constructor.
     *
     * @param TransactionsApi $transactionsApi
     * @param LoggerInterface $logger
     */
    public function __construct(TransactionsApi $transactionsApi, LoggerInterface $logger)
    {
        $this->transactionsApi = $transactionsApi;
        $this->setLogger($logger);
    }

    /**
     * @param string $budgetName
     * @param Account $account
     * @param BankTransaction[] $newTransactions
     * @param array $namePayeeMap
     * @throws \YNAB\ApiException
     * @throws \Exception
     */
    public function buildTransactionsFromHbciTransactions(
        string $budgetName,
        Account $account,
        array $newTransactions,
        array $namePayeeMap
    ): void {
        $fees = array_filter($newTransactions, function (Transaction $transaction) {
            return $transaction->getBookingText() === 'Gebühren';
        });
        $debits = array_filter($newTransactions, function (Transaction $transaction) {
            return $transaction->getBookingText() === 'Lastschrift (Einzugsermächtigung)';
        });
        $bonuses = array_filter($newTransactions, function (Transaction $transaction) {
            return \in_array($transaction->getBookingText(),
                    ['EURO-Überweisung', 'Überweisungsgutschrift', 'Gehalt/Rente'], true)
                && !preg_match('#^[0-9\.\-]+% AUSLANDSEINSATZENTG$#', $transaction->getName());
        });
        $feeBonuses = array_filter($newTransactions, function (Transaction $transaction) {
            return $transaction->getBookingText() === 'Überweisungsgutschrift'
                && preg_match('#^[0-9\.\-]+% AUSLANDSEINSATZENTG$#', $transaction->getName());
        });
        foreach ($newTransactions as $transaction) {
            /** @noinspection AdditionOperationOnArraysInspection */
            if (!\in_array($transaction, $fees + $debits + $bonuses + $feeBonuses, false)) {
                throw new \RuntimeException('Unknown Transaction found: ' . $transaction->getBookingText());
            }
        }

        foreach ($debits as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction);

            foreach ($fees as $nr => $feeTransaction) {
                if ($transaction instanceof CreditCardTransaction && $feeTransaction instanceof CreditCardTransaction &&
                    $transaction->getCcValue() === $feeTransaction->getCcValue()) {

                    $memoPrefix = 'Fee for ' . number_format($transaction->getAmount(), 2, ',', '.');
                    $payeeName = $transaction->getName();
                    $this->createNewTransaction($budgetName, $account, $namePayeeMap, $feeTransaction, $memoPrefix,
                        $transaction->getName(), $payeeName);

                    unset($fees[$nr]);
                }
            }

        }
        foreach ($fees as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction);
        }
        foreach ($bonuses as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction);

            foreach ($feeBonuses as $nr => $feeTransaction) {
                if ($transaction instanceof CreditCardTransaction && $feeTransaction instanceof CreditCardTransaction &&
                    $transaction->getCcValue() === $feeTransaction->getCcValue()) {

                    $memoPrefix = 'Fee for ' . number_format(-1 * $transaction->getAmount(), 2, ',', '.');
                    $payeeName = $transaction->getName();
                    $this->createNewTransaction($budgetName, $account, $namePayeeMap, $feeTransaction, $memoPrefix,
                        $transaction->getName(), $payeeName);

                    unset($feeBonuses[$nr]);
                }
            }

        }
        foreach ($feeBonuses as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction);
        }
    }

    /**
     * @param string $budgetName
     * @param Account $account
     * @param PayPalTransaction[] $newTransactions
     * @param array $namePayeeMap
     * @throws \YNAB\ApiException
     * @throws \Exception
     */
    public function buildTransactionsFromPayPalTransactions(
        string $budgetName,
        Account $account,
        array $newTransactions,
        array $namePayeeMap
    ): void {
        $payments = array_filter($newTransactions, function (PayPalTransaction $transaction) {
            return $transaction->getType() === PayPalTransaction::TRANSACTION_TYPE_PAYMENT;
        });
        $transfers = array_filter($newTransactions, function (PayPalTransaction $transaction) {
            return $transaction->getType() === PayPalTransaction::TRANSACTION_TYPE_TRANSFER;
        });
        foreach ($newTransactions as $transaction) {
            /** @noinspection AdditionOperationOnArraysInspection */
            if (!\in_array($transaction, $payments + $transfers, false)) {
                throw new \RuntimeException('Unknown Transaction found: ' . $transaction->getBookingText());
            }
        }
        foreach ($payments as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction);
        }
        foreach ($transfers as $transaction) {
            $this->createNewTransaction($budgetName, $account, $namePayeeMap, $transaction,'Transfer-Buchung: ');
        }
    }

    /**
     * @param string $budgetName
     * @param Account $account
     * @param string[] $namePayeeMap
     * @param YnabCompatibleTransaction $transaction
     * @param string $memoPrefix
     * @param string|null $overrideTransactionName
     * @param string|null $overridePayeeName
     * @throws \YNAB\ApiException
     */
    protected function createNewTransaction(
        string $budgetName,
        Account $account,
        array $namePayeeMap,
        YnabCompatibleTransaction $transaction,
        $memoPrefix = '',
        ?string $overrideTransactionName = null,
        ?string $overridePayeeName = null
    ): void {
        $newTransactionWrapper = new SaveTransactionWrapper();
        $newTransaction = new SaveTransaction();
        $newTransactionWrapper->setTransaction($newTransaction);

        $newTransaction->setAccountId($account->getId());
        $amount = (int)($transaction->getAmount() * 1000);
        $newTransaction->setAmount($amount);
        $newTransaction->setDate(\DateTime::createFromFormat(\DateTimeInterface::RFC3339,
            $transaction->getDate()->format(\DateTimeInterface::RFC3339)));
        $newTransaction->setApproved(false);
        $mainDescription = ($memoPrefix ? $memoPrefix . ' - ' : '') .
            ($overrideTransactionName ?? $transaction->getPayer() ? $transaction->getPayer() . ' - ' : '') . $transaction->getDescription();
        $newTransaction->setMemo(\strlen($mainDescription) > 100
            ? substr($mainDescription, 0, 97) . '...' : $mainDescription);
        if (isset($namePayeeMap[$overridePayeeName ?? $transaction->getPayer()])) {
            $newTransaction->setPayeeId($namePayeeMap[$overridePayeeName ?? $transaction->getPayer()]);
        } else {
            $newTransaction->setPayeeName(ucwords(mb_strtolower($overridePayeeName ?? $transaction->getPayer())));
        }
        $newTransaction->setFlagColor(SaveTransaction::FLAG_COLOR_YELLOW);
        if ($transaction instanceof BankTransaction) {
            $this->logger->info('Could not find a matching YNAB-transaction for this hbci transaction:' . PHP_EOL .
                'Amount         : ' . ($transaction->getCreditDebit() === Transaction::CD_DEBIT ? '-' : '') . $transaction->getAmount() . PHP_EOL .
                'Booking text   : ' . $transaction->getBookingText() . PHP_EOL .
                'Name           : ' . $transaction->getName() . PHP_EOL .
                'Description1   : ' . $transaction->getDescription1() . PHP_EOL .
                'MainDescription: ' . $transaction->getMainDescription() . PHP_EOL .
                'BookingDate    : ' . ($transaction->getBookingDate() ? $transaction->getBookingDate()->format('Y-m-d') : '') . PHP_EOL .
                'ValutaDate     : ' . ($transaction->getValutaDate() ? $transaction->getValutaDate()->format('Y-m-d') : ''));
        } else {
            $this->logger->info('Could not find a matching YNAB-transaction for this transaction:' . PHP_EOL .
                'Amount         : ' . $transaction->getAmount() . PHP_EOL .
                'Name           : ' . $transaction->getPayer() . PHP_EOL .
                'MainDescription: ' . $transaction->getDescription() . PHP_EOL .
                'BookingDate    : ' . ($transaction->getDate() ? $transaction->getDate()->format('Y-m-d') : ''));
        }
        $this->logger->info('Create new Transaction: ' . $transaction);
        $this->transactionsApi->createTransaction($budgetName, $newTransactionWrapper);
    }
}
