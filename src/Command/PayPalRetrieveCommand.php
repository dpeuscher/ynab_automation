<?php

namespace App\Command;

use App\Bank\Transformer\PayPalTransactionArrayToPayPalTransactionTransformer;
use App\Paypal\RetrievePayPalTransactionService;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionBuilder;
use App\Ynab\TransactionRetriever;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class PayPalRetrieveCommand extends ContainerAwareCommand
{
    /**
     * @var RetrievePayPalTransactionService
     */
    protected $retrievePayPalTransactionService;

    /**
     * @var PayPalTransactionArrayToPayPalTransactionTransformer
     */
    protected $payPalTransactionArrayToPayPalTransactionTransformer;

    /**
     * @var TransactionRetriever
     */
    private $transactionRetriever;

    /**
     * @var TransactionAnalyser
     */
    private $transactionAnalyser;

    /**
     * @var TransactionBuilder
     */
    private $transactionBuilder;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->retrievePayPalTransactionService = $this->getContainer()->get(RetrievePayPalTransactionService::class);
        $this->payPalTransactionArrayToPayPalTransactionTransformer = $this->getContainer()->get(PayPalTransactionArrayToPayPalTransactionTransformer::class);
        $this->transactionRetriever = $this->getContainer()->get(TransactionRetriever::class);
        $this->transactionAnalyser = $this->getContainer()->get(TransactionAnalyser::class);
        $this->transactionBuilder = $this->getContainer()->get(TransactionBuilder::class);
    }

    protected function configure(): void
    {
        $this
            ->setName('paypal:retrieve')
            ->addArgument(
                'fromDateInterval',
                InputArgument::REQUIRED
            )->addArgument(
                'toDate',
                InputArgument::OPTIONAL
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        try {
            $now = new \DateTime();
            $from = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));
            $to = new \DateTime($input->getArgument('toDate'));
            $accountName = 'Paypal dpeuscher@gmail.com';
            $budget = $this->getContainer()->getParameter('ynab_budget');

            $fromYnab = clone $from;
            $fromYnab->sub(new \DateInterval('P7D'));

            $account = $this->transactionRetriever->findAccount($budget, $accountName);

            $ynabTransactions =
                $this->transactionRetriever->retrieveTransactionsForAccount($budget, $accountName, $fromYnab, $account);
            $ynabScheduledTransactions =
                $this->transactionRetriever->retrieveScheduledTransactionsForAccount($budget, $accountName, $account);

            $transactionArrays = $this->retrievePayPalTransactionService->getTransactions($from, $to);
            $transactions = [];
            foreach ($transactionArrays as $transactionArray) {
                $transactions[] = $this->payPalTransactionArrayToPayPalTransactionTransformer->transform($transactionArray);
            }

            // Replace HbciTransaction by Transaction
            $newYnabTransactions =
                $this->transactionAnalyser->findNewYnabTransactions($transactions, $ynabTransactions,
                    $ynabScheduledTransactions);

            $namePayeeMap = $this->transactionAnalyser->buildNamePayeeMapByClosestMatch($transactions,
                $ynabTransactions, $ynabScheduledTransactions);

            $this->transactionBuilder->buildTransactionsFromPayPalTransactions($budget, $account, $newYnabTransactions,
                $namePayeeMap);
            echo 'Found ' . \count($transactions) . ' transactions.' . PHP_EOL;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL . $e;
        }
    }

}
