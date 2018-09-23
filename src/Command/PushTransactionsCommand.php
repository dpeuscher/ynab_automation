<?php

namespace App\Command;

use App\Bank\RetrieveTransactionService;
use App\Bank\Transformer\TransactionToHbciTransactionTransformer;
use App\Entity\HbciTransaction;
use App\Repository\HbciTransactionRepository;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionRetriever;
use Doctrine\ORM\EntityManager;
use Exception;
use Fhp\Adapter\Exception\AdapterException;
use Fhp\Adapter\Exception\CurlException;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class PushTransactionsCommand extends ContainerAwareCommand
{
    /**
     * @var RetrieveTransactionService
     */
    private $retrieveTransactionService;

    /**
     * @var TransactionRetriever
     */
    private $transactionRetriever;

    /**
     * @var TransactionAnalyser
     */
    private $transactionAnalyser;

    /**
     * @var TransactionToHbciTransactionTransformer
     */
    private $transactionToHbciTransactionTransformer;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var HbciTransactionRepository
     */
    private $hbciTransactionRepository;

    /**
     * @var string
     */
    private $iftttWebhookKey;

    /**
     * @var string
     */
    private $iftttWebhookName;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->transactionRetriever = $this->getContainer()->get(TransactionRetriever::class);
        $this->transactionAnalyser = $this->getContainer()->get(TransactionAnalyser::class);
        $this->transactionToHbciTransactionTransformer = $this->getContainer()->get(TransactionToHbciTransactionTransformer::class);
        $this->entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->hbciTransactionRepository = $this->entityManager->getRepository(HbciTransaction::class);
        $this->iftttWebhookKey = $this->getContainer()->getParameter('ifttt_webhook_key');
        $this->iftttWebhookName = $this->getContainer()->getParameter('ifttt_webhook_name');
    }

    protected function configure()
    {
        $this
            ->setName('hbci:push')
            ->addOption(
                'bankNumber',
                'b',
                InputOption::VALUE_REQUIRED,
                'Choose the bank to use',
                '1'
            )->addArgument(
                'fromDateInterval',
                InputArgument::OPTIONAL
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $bankNumber = $input->getOption('bankNumber');
            if (!\in_array($bankNumber, ['1', '2'], true)) {
                throw new \RuntimeException('Currently only 2 banks are supported');
            }
            $this->retrieveTransactionService = $this->getContainer()->get('retrieve:transaction:service:' . $bankNumber);

            $now = new \DateTime();
            $from = clone $now;
            $to = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));

            $transactions = $this->retrieveTransactionService->getTransactions($from, $to);

            /** @var HbciTransaction[] $newTransactions */
            $newTransactions = [];

            foreach ($transactions as $transaction) {
                $hbciTransaction = $this->transactionToHbciTransactionTransformer->transform($transaction);
                $matches = $this->hbciTransactionRepository->findByChecksum($hbciTransaction->getChecksum());
                if (empty($matches)) {
                    $newTransactions[] = $hbciTransaction;
                }
            }

            foreach ($newTransactions as $transaction) {
                $this->pushMessage($transaction);
                $this->entityManager->persist($transaction);
                $this->entityManager->flush();
            }

            echo 'Found ' . \count($newTransactions) . ' new transactions.' . PHP_EOL;
        } catch (CurlException $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        } catch (AdapterException $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        }
        exit;
    }

    /**
     * @param HbciTransaction $transaction
     */
    protected function pushMessage($transaction): void
    {
        $client = new Client();
        $issuer = ucwords(strtolower($transaction->getName()));
        $value = number_format($transaction->getAmount(), 2, ',', '.') . ' €';
        if ($transaction->getCcPostingLocation() === null) {
            $description = $transaction->getStructuredDescription()['SVWZ'];
        } else {
            $description = ucwords(strtolower($transaction->getCcPostingLocation())) . ' ' . $transaction->getCcIdentifier() . ' ' .
                ($transaction->getCcCurrency() !== 'EUR'
                    ? ' ' . $transaction->getCcValue() . ' ' . $transaction->getCcCurrency() . ' ' . $transaction->getCcExchangeRate() .
                    ' ' . $transaction->getCcCurrency() . ' / EUR'
                    : '');
        }
        $client->post('https://maker.ifttt.com/trigger/' . $this->iftttWebhookName . '/with/key/' . $this->iftttWebhookKey,
            [
                RequestOptions::JSON => [
                    'value1' => $issuer,
                    'value2' => $value . ' Bank',
                    'value3' => $description,
                ],
            ]);
    }

}
