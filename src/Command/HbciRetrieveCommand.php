<?php

namespace App\Command;

use App\Bank\RetrieveTransactionService;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionBuilder;
use App\Ynab\TransactionRetriever;
use Exception;
use Fhp\Adapter\Exception\AdapterException;
use Fhp\Adapter\Exception\CurlException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class HbciRetrieveCommand extends ContainerAwareCommand
{
    /**
     * @var RetrieveTransactionService
     */
    protected $retrieveTransactionService;

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
        $this->retrieveTransactionService = $this->getContainer()->get('retrieve:transaction:service:1');
        $this->transactionRetriever = $this->getContainer()->get(TransactionRetriever::class);
        $this->transactionAnalyser = $this->getContainer()->get(TransactionAnalyser::class);
        $this->transactionBuilder = $this->getContainer()->get(TransactionBuilder::class);
    }

    protected function configure(): void
    {
        $this
            ->setName('hbci:retrieve')
            ->addOption(
                'bankNumber',
                'b',
                InputOption::VALUE_REQUIRED,
                'Choose the bank to use',
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
                throw new \RuntimeException('Currently only 2 banks are supported');
            }
            $this->retrieveTransactionService = $this->getContainer()->get('retrieve:transaction:service:' . $bankNumber);
            $accountName = $this->getContainer()->getParameter('ynab_account_name_' . $bankNumber);

            $now = new \DateTime();
            $from = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));
            $to = new \DateTime($input->getArgument('toDate'));
            $budget = $this->getContainer()->getParameter('ynab_budget_' . $bankNumber);

            $fromYnab = clone $from;
            $fromYnab->sub(new \DateInterval('P7D'));

            $account = $this->transactionRetriever->findAccount($budget, $accountName);

            $ynabTransactions =
                $this->transactionRetriever->retrieveTransactionsForAccount($budget, $accountName, $fromYnab, $account);
            $ynabScheduledTransactions =
                $this->transactionRetriever->retrieveScheduledTransactionsForAccount($budget, $accountName, $account);

            $hbciTransactions = $this->retrieveTransactionService->getTransactions($from, $to);

            $newYnabTransactions =
                $this->transactionAnalyser->findNewYnabTransactions($hbciTransactions, $ynabTransactions,
                    $ynabScheduledTransactions);

            $namePayeeMap = $this->transactionAnalyser->buildNamePayeeMapByClosestMatch($hbciTransactions,
                $ynabTransactions, $ynabScheduledTransactions);

            $this->transactionBuilder->buildTransactionsFromHbciTransactions($budget, $account, $newYnabTransactions,
                $namePayeeMap);
            echo 'Found ' . \count($hbciTransactions) . ' hbciTransactions.' . PHP_EOL;
        } catch (CurlException $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        } catch (AdapterException $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL . $e;
        }
    }

}
