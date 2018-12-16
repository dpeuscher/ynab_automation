<?php

namespace App\Command;

use App\Boon\RetrieveBoonTransactionService;
use App\Boon\Transformer\BoonTransactionArrayToBoonTransactionTransformer;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionBuilder;
use App\Ynab\TransactionRetriever;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class BoonRetrieveCommand extends ContainerAwareCommand
{
    /**
     * @var RetrieveBoonTransactionService
     */
    protected $retrieveBoonTransactionService;

    /**
     * @var BoonTransactionArrayToBoonTransactionTransformer
     */
    protected $boonTransactionArrayToBoonTransactionTransformer;

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
        $this->boonTransactionArrayToBoonTransactionTransformer = $this->getContainer()->get(BoonTransactionArrayToBoonTransactionTransformer::class);
        $this->transactionRetriever = $this->getContainer()->get(TransactionRetriever::class);
        $this->transactionAnalyser = $this->getContainer()->get(TransactionAnalyser::class);
        $this->transactionBuilder = $this->getContainer()->get(TransactionBuilder::class);
    }

    protected function configure(): void
    {
        $this
            ->setName('boon:retrieve')
            ->addOption(
                'bankNumber',
                'b',
                InputOption::VALUE_REQUIRED,
                'Choose the account to use',
                '1'
            )->addArgument(
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
            $bankNumber = $input->getOption('bankNumber');
            if (!\in_array($bankNumber, ['1', '2'], true)) {
                throw new \RuntimeException('Currently only 2 accounts are supported');
            }
            $this->retrieveBoonTransactionService = $this->getContainer()->get('retrieve:boontransaction:service:' . $bankNumber);

            $now = new \DateTime();
            $from = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));
            $to = new \DateTime($input->getArgument('toDate'));
            $accountName = $this->getContainer()->getParameter('ynab_account_boon_' . $bankNumber);
            $budget = $this->getContainer()->getParameter('ynab_budget_1');

            $fromYnab = clone $from;
            $fromYnab->sub(new \DateInterval('P7D'));

            $account = $this->transactionRetriever->findAccount($budget, $accountName);

            $ynabTransactions =
                $this->transactionRetriever->retrieveTransactionsForAccount($budget, $accountName, $fromYnab, $account);
            $ynabScheduledTransactions =
                $this->transactionRetriever->retrieveScheduledTransactionsForAccount($budget, $accountName, $account);

            $transactionArrays = $this->retrieveBoonTransactionService->getTransactions($from, $to);
            $transactions = [];
            foreach ($transactionArrays as $transactionArray) {
                $transactions[] = $this->boonTransactionArrayToBoonTransactionTransformer->transform($transactionArray);
            }

            // Replace HbciTransaction by Transaction
            $newYnabTransactions =
                $this->transactionAnalyser->findNewYnabTransactions($transactions, $ynabTransactions,
                    $ynabScheduledTransactions);

            $namePayeeMap = $this->transactionAnalyser->buildNamePayeeMapByClosestMatch($transactions,
                $ynabTransactions, $ynabScheduledTransactions);

            $this->transactionBuilder->buildTransactionsFromBoonTransactions($budget, $account, $newYnabTransactions,
                $namePayeeMap);
            echo 'Found ' . \count($transactions) . ' transactions.' . PHP_EOL;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL . $e;
        }
    }

}
