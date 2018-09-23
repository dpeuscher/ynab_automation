<?php

namespace App\Command;

use App\Bank\Transformer\PayPalTransactionArrayToPayPalTransactionTransformer;
use App\Entity\PayPalTransaction;
use App\Paypal\RetrievePayPalTransactionService;
use App\Repository\PayPalTransactionRepository;
use App\Ynab\TransactionAnalyser;
use App\Ynab\TransactionRetriever;
use Doctrine\ORM\EntityManager;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class PushPaypalTransactionsCommand extends ContainerAwareCommand
{
    /**
     * @var RetrievePayPalTransactionService
     */
    private $retrievePayPalTransactionService;

    /**
     * @var TransactionRetriever
     */
    private $transactionRetriever;

    /**
     * @var TransactionAnalyser
     */
    private $transactionAnalyser;

    /**
     * @var PayPalTransactionArrayToPayPalTransactionTransformer
     */
    private $payPalTransactionArrayToPayPalTransactionTransformer;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var PayPalTransactionRepository
     */
    private $payPalTransactionRepository;

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
        $this->retrievePayPalTransactionService = $this->getContainer()->get(RetrievePayPalTransactionService::class);
        $this->transactionRetriever = $this->getContainer()->get(TransactionRetriever::class);
        $this->transactionAnalyser = $this->getContainer()->get(TransactionAnalyser::class);
        $this->payPalTransactionArrayToPayPalTransactionTransformer = $this->getContainer()->get(PayPalTransactionArrayToPayPalTransactionTransformer::class);
        $this->entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $this->payPalTransactionRepository = $this->entityManager->getRepository(PayPalTransaction::class);
        $this->iftttWebhookKey = $this->getContainer()->getParameter('ifttt_webhook_key');
        $this->iftttWebhookName = $this->getContainer()->getParameter('ifttt_webhook_name');
    }

    protected function configure()
    {
        $this
            ->setName('paypal:push')
            ->addArgument(
                'fromDateInterval',
                InputArgument::OPTIONAL
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $now = new \DateTime();
            $from = clone $now;
            $to = clone $now;
            $from->sub(new \DateInterval($input->getArgument('fromDateInterval')));

            $transactions = $this->retrievePayPalTransactionService->getTransactions($from, $to);

            /** @var PayPalTransaction[] $newTransactions */
            $newTransactions = [];

            foreach ($transactions as $transaction) {
                $paypalTransaction = $this->payPalTransactionArrayToPayPalTransactionTransformer->transform($transaction);
                $matches = $this->payPalTransactionRepository->findByChecksum($paypalTransaction->getChecksum());
                if (empty($matches)) {
                    $newTransactions[] = $paypalTransaction;
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
     * @param PayPalTransaction $transaction
     */
    protected function pushMessage(PayPalTransaction $transaction): void
    {
        $client = new Client();
        $issuer = ucwords(strtolower($transaction->getPayer()));
        $value = number_format($transaction->getAmount(), 2, ',', '.') . ' €';
        $description = $transaction->getDescription();
        $client->post('https://maker.ifttt.com/trigger/' . $this->iftttWebhookName . '/with/key/' . $this->iftttWebhookKey,
            [
                RequestOptions::JSON => [
                    'value1' => $issuer,
                    'value2' => $value . ' PayPal',
                    'value3' => $description,
                ],
            ]);
    }

}
