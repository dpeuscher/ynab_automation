<?php

namespace App\Command;

use App\Boon\RetrieveBoonTransactionService;
use App\Boon\Transformer\BoonTransactionArrayToBoonTransactionTransformer;
use App\Entity\BoonTransaction;
use App\Repository\BoonTransactionRepository;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionRetriever;
use Doctrine\ORM\EntityManager;
use Exception;
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
class PushBoonTransactionsCommand extends ContainerAwareCommand
{
    /**
     * @var RetrieveBoonTransactionService
     */
    private $retrieveBoonTransactionService;

    /**
     * @var TransactionRetriever
     */
    private $transactionRetriever;

    /**
     * @var TransactionAnalyser
     */
    private $transactionAnalyser;

    /**
     * @var BoonTransactionArrayToBoonTransactionTransformer
     */
    private $boonTransactionArrayToBoonTransactionTransformer;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var BoonTransactionRepository
     */
    private $boonTransactionRepository;

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
        $this->boonTransactionArrayToBoonTransactionTransformer = $this->getContainer()->get(BoonTransactionArrayToBoonTransactionTransformer::class);
        $this->entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->boonTransactionRepository = $this->entityManager->getRepository(BoonTransaction::class);
        $this->iftttWebhookKey = $this->getContainer()->getParameter('ifttt_webhook_key');
        $this->iftttWebhookName = $this->getContainer()->getParameter('ifttt_webhook_name');
    }

    protected function configure()
    {
        $this
            ->setName('boon:push')
            ->addOption(
                'bankNumber',
                'b',
                InputOption::VALUE_REQUIRED,
                'Choose the account to use',
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
                throw new \RuntimeException('Currently only 2 accounts are supported');
            }
            $this->retrieveBoonTransactionService = $this->getContainer()->get('retrieve:boontransaction:service:' . $bankNumber);

            $now = new \DateTime();
            $from = clone $now;
            $to = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));

            $transactions = $this->retrieveBoonTransactionService->getTransactions($from, $to);

            /** @var BoonTransaction[] $newTransactions */
            $newTransactions = [];

            foreach ($transactions as $transaction) {
                $boonTransaction = $this->boonTransactionArrayToBoonTransactionTransformer->transform($transaction);
                $matches = $this->boonTransactionRepository->findByChecksum($boonTransaction->getChecksum());
                if (empty($matches)) {
                    $newTransactions[] = $boonTransaction;
                }
            }

            foreach ($newTransactions as $transaction) {
                $this->pushMessage($transaction);
                $this->entityManager->persist($transaction);
                $this->entityManager->flush();
            }

            echo 'Found ' . \count($newTransactions) . ' new transactions.' . PHP_EOL;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage() . '' . PHP_EOL;
        }
        exit;
    }

    /**
     * @param BoonTransaction $transaction
     */
    protected function pushMessage(BoonTransaction $transaction): void
    {
        $client = new Client();
        $issuer = mb_convert_case(mb_strtolower($transaction->getPayer()), MB_CASE_TITLE, 'UTF-8');
        $value = number_format($transaction->getAmount(), 2, ',', '.') . ' €';
        $description = $transaction->getDescription();
        $client->post('https://maker.ifttt.com/trigger/' . $this->iftttWebhookName . '/with/key/' . $this->iftttWebhookKey,
            [
                RequestOptions::JSON => [
                    'value1' => $issuer,
                    'value2' => $value . ' Boon',
                    'value3' => $description,
                ],
            ]);
    }

}
