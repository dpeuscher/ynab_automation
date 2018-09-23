<?php

namespace App\Ynab;

use YNAB\Client\AccountsApi;
use YNAB\Client\ScheduledTransactionsApi;
use YNAB\Client\TransactionsApi;
use YNAB\Model\Account;
use YNAB\Model\ScheduledTransactionDetail;
use YNAB\Model\TransactionDetail;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class TransactionRetriever
{
    /**
     * @var AccountsApi
     */
    private $accountsApi;

    /**
     * @var TransactionsApi
     */
    private $transactionsApi;

    /**
     * @var ScheduledTransactionsApi
     */
    private $scheduledTransactionsApi;

    /**
     * TransactionRetriever constructor.
     *
     * @param AccountsApi $accountsApi
     * @param TransactionsApi $transactionsApi
     * @param ScheduledTransactionsApi $scheduledTransactionsApi
     */
    public function __construct(
        AccountsApi $accountsApi,
        TransactionsApi $transactionsApi,
        ScheduledTransactionsApi $scheduledTransactionsApi
    ) {
        $this->accountsApi = $accountsApi;
        $this->transactionsApi = $transactionsApi;
        $this->scheduledTransactionsApi = $scheduledTransactionsApi;
    }

    /**
     * @param string $budgetName
     * @param string $accountName
     * @return Account
     * @throws \YNAB\ApiException
     * @throws \Exception
     */
    public function findAccount(string $budgetName, string $accountName): Account
    {
        $foundAccount = null;
        $accounts = $this->accountsApi->getAccounts($budgetName)->getData()->getAccounts();
        foreach ($accounts as $account) {
            if ($account->getName() === $accountName) {
                $foundAccount = $account;
                break;
            }
        }
        if ($foundAccount === null) {
            throw new \RuntimeException('Could not find account ' . $accountName);
        }
        return $foundAccount;
    }

    /**
     * @param string $budgetName
     * @param string $accountName
     * @param \DateTime $from
     * @param null|Account $account
     * @return TransactionDetail[][]
     * @throws \YNAB\ApiException
     */
    public function retrieveTransactionsForAccount(
        string $budgetName,
        string $accountName,
        \DateTime $from,
        ?Account $account
    ): array {
        if ($account === null) {
            $account = $this->findAccount($budgetName, $accountName);
        }

        /** @var TransactionDetail[] $transactionDetails */
        $transactionDetails = $this->transactionsApi
            ->getTransactionsByAccount($budgetName, $account->getId(), $from)->getData()->getTransactions();

        foreach ($transactionDetails as $transactionDetail) {
            $transactionDetail->setAmount(round($transactionDetail->getAmount() / 1000,2)*1000);
        }

        return $transactionDetails;
    }

    /**
     * @param string $budgetName
     * @param string $accountName
     * @param null|Account $account
     * @return ScheduledTransactionDetail[][]
     * @throws \YNAB\ApiException
     */
    public function retrieveScheduledTransactionsForAccount(
        string $budgetName,
        string $accountName,
        ?Account $account
    ): array {
        if ($account === null) {
            $account = $this->findAccount($budgetName, $accountName);
        }

        /** @var ScheduledTransactionDetail[] $prices */
        $scheduledTransactionDetails = $this->scheduledTransactionsApi
            ->getScheduledTransactions($budgetName)->getData()->getScheduledTransactions();

        foreach ($scheduledTransactionDetails as $transactionDetail) {
            $transactionDetail->setAmount(round($transactionDetail->getAmount() / 1000, 2) * 1000);
        }

        $scheduledTransactionDetailsByAccount = array_filter($scheduledTransactionDetails,
            function (ScheduledTransactionDetail $transaction) use ($account) {
                return $transaction->getAccountId() === $account->getId();
            });

        return $scheduledTransactionDetailsByAccount;
    }
}
