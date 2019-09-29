<?php

namespace App\Paypal;

use App\Entity\PayPalTransaction;
use PayPal\Core\PayPalHttpConfig;
use PayPal\Handler\RestHandler;
use PayPal\Transport\PayPalRestCall;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class RetrievePayPalTransactionService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @see https://developer.paypal.com/docs/integration/direct/sync/transaction-event-codes/
     */
    private const TRANSACTION_TYPE_CODE_PAYPAL_PAYPAL = 'T00';

    private const TRANSACTION_TYPE_CODE_FEE = 'T01';

    private const TRANSACTION_TYPE_CODE_CURRENCY_CONVERSION = 'T02';

    private const TRANSACTION_TYPE_CODE_BANK_DEPOSIT = 'T03';

    private const TRANSACTION_TYPE_CODE_BANK_WITHDRAWAL = 'T04';

    private const TRANSACTION_TYPE_CODE_DEBIT_CARD = 'T05';

    private const TRANSACTION_TYPE_CODE_CREDIT_CARD_WITHDRAWAL = 'T06';

    private const TRANSACTION_TYPE_CODE_CREDIT_CARD_DEPOSIT = 'T07';

    private const TRANSACTION_TYPE_CODE_BONUS = 'T08';

    private const TRANSACTION_TYPE_CODE_INCENTIVE = 'T09';

    private const TRANSACTION_TYPE_CODE_BILL_PAY = 'T10';

    private const TRANSACTION_TYPE_CODE_REVERSAL = 'T11';

    private const TRANSACTION_TYPE_CODE_ADJUSTMENT = 'T12';

    private const TRANSACTION_TYPE_CODE_AUTHORIZATION = 'T13';

    private const TRANSACTION_TYPE_CODE_DIVIDENT = 'T14';

    private const TRANSACTION_TYPE_CODE_HOLD = 'T15';

    private const TRANSACTION_TYPE_CODE_BUYER_CREDIT = 'T16';

    private const TRANSACTION_TYPE_CODE_NON_BANK_WITHDRAWAL = 'T17';

    private const TRANSACTION_TYPE_CODE_BUYER_CREDIT_WITHDRAWAL = 'T18';

    private const TRANSACTION_TYPE_CODE_ACCOUNT_CORRECTION = 'T19';

    private const TRANSACTION_TYPE_CODE_PAYPAL_OTHER = 'T20';

    private const TRANSACTION_TYPE_CODE_RESERVES_AND_RELEASES = 'T21';

    private const TRANSACTION_TYPE_CODE_TRANSACTIONS = 'T22';

    private const TRANSACTION_TYPE_CODE_GENERIC_INSTRUMENT = 'T30';

    private const TRANSACTION_TYPE_CODE_COLLECTIONS_DISBURSEMENTS = 'T50';

    private const TRANSACTION_TYPE_CODE_PAYABLES = 'T97';

    private const TRANSACTION_TYPE_CODE_DISPLAY_ONLY = 'T98';

    private const TRANSACTION_TYPE_CODE_OTHER = 'T99';

    /**
     * @var RestHandler
     */
    private $paypalRestHandler;

    /**
     * @var PayPalRestCall
     */
    private $paypalRestCall;

    public function __construct(RestHandler $paypalRestHandler, PayPalRestCall $paypalRestCall, LoggerInterface $logger)
    {
        $this->paypalRestHandler = $paypalRestHandler;
        $this->paypalRestCall = $paypalRestCall;
        $this->logger = $logger;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     * @throws \Exception
     */
    public function getTransactions(\DateTime $from, \DateTime $to): array
    {
        $from = max($from, \DateTime::createFromFormat('Y-m-d', '2018-09-15'));
        $response = json_decode($this->paypalRestCall->execute(
            [$this->paypalRestHandler],
            '/v1/reporting/transactions?' .
            \http_build_query([
                'start_date'                     => $from->format('Y-m-d\TH:i:s.000\Z'),
                'end_date'                       => $to->format('Y-m-d\TH:i:s.000\Z'),
                'page_size'                      => '100',
                'page'                           => '1',
                'fields'                         => 'all',
                'balance_affecting_records_only' => 'Y',
            ]),
            PayPalHttpConfig::HTTP_GET
        ));

        $transactions = [];
        $compare = clone $to;
        $compare->add(new \DateInterval('P1D'));
        foreach ($response->transaction_details as $transaction) {
            unset($itemString);
            if (isset($transaction->cart_info, $transaction->cart_info->item_details)) {
                $items = [];
                foreach ($transaction->cart_info->item_details as $itemDetails) {
                    if (!isset($itemDetails->item_name)) {
                        continue;
                    }
                    $items[] = $itemDetails->item_name;
                }
                $itemString = implode(', ', $items);
            }
            $transactionArray = [
                'transactionId'          => $transaction->transaction_info->transaction_id ?? null,
                'transactionReferenceId' => $transaction->transaction_info->paypal_reference_id ?? null,
                'date'                   => \DateTime::createFromFormat('Y-m-d\TH:i:s+O',
                    $transaction->transaction_info->transaction_updated_date ?? $transaction->transaction_info->transaction_initiation_date),
                'amount'                 => $transaction->transaction_info->transaction_amount->value ?? null,
                'description'            => trim(''
                    . ($transaction->transaction_info->transaction_event_code ?? '') . ','
                    . ($transaction->transaction_info->transaction_note ?? '') . ','
                    . ($transaction->payer_info->email_address ?? '') . ','
                    . ($transaction->transaction_info->bank_reference_id ?? '') . ','
                    . ($transaction->transaction_info->invoice_id ?? '') . ','
                    . ($transaction->transaction_info->custom_field ?? '') . ','
                    . ($itemString ?? '')
                ),
                'payer'                  => $transaction->payer_info->payer_name->alternate_full_name ?? $transaction->payer_info->email_address ?? '',
            ];
            $transactionTypeGroup = substr($transaction->transaction_info->transaction_event_code, 0, 3);
            switch ($transactionTypeGroup) {
                case self::TRANSACTION_TYPE_CODE_BANK_DEPOSIT:
                case self::TRANSACTION_TYPE_CODE_BANK_WITHDRAWAL:
                case self::TRANSACTION_TYPE_CODE_DEBIT_CARD:
                case self::TRANSACTION_TYPE_CODE_CREDIT_CARD_WITHDRAWAL:
                case self::TRANSACTION_TYPE_CODE_CREDIT_CARD_DEPOSIT:
                case self::TRANSACTION_TYPE_CODE_NON_BANK_WITHDRAWAL:
                case self::TRANSACTION_TYPE_CODE_BUYER_CREDIT_WITHDRAWAL:
                    $transactionArray['type'] = PayPalTransaction::TRANSACTION_TYPE_TRANSFER;
                    if ($transactionArray['payer'] === null) {
                        foreach ($transactions as $compareTransaction) {
                            if ($transactionArray['transactionReferenceId'] !== null &&
                                $compareTransaction['payer'] !== null &&
                                ($transactionArray['transactionReferenceId'] === $compareTransaction['transactionId'] ||
                                    $transactionArray['transactionReferenceId'] === $compareTransaction['transactionReferenceId'])
                            ) {
                                $transactionArray['payer'] = $compareTransaction['payer'];
                                break;
                            }
                        }
                    }
                    break;
                case self::TRANSACTION_TYPE_CODE_PAYPAL_PAYPAL:
                case self::TRANSACTION_TYPE_CODE_FEE:
                case self::TRANSACTION_TYPE_CODE_CURRENCY_CONVERSION:
                case self::TRANSACTION_TYPE_CODE_BONUS:
                case self::TRANSACTION_TYPE_CODE_INCENTIVE:
                case self::TRANSACTION_TYPE_CODE_BILL_PAY:
                case self::TRANSACTION_TYPE_CODE_ACCOUNT_CORRECTION:
                case self::TRANSACTION_TYPE_CODE_TRANSACTIONS:
                case self::TRANSACTION_TYPE_CODE_REVERSAL:
                    $transactionArray['type'] = PayPalTransaction::TRANSACTION_TYPE_PAYMENT;
                    break;
                default:
                    $transactionArray['type'] = null;
            }
            if (($transaction->transaction_info->transaction_amount->currency_code ?? '') !== 'EUR' ||
                ($transaction->transaction_info->transaction_status ?? null) !== 'S' ||
                $transactionArray['amount'] === null ||
                $transactionArray['type'] === null ||
                ($transactionArray['date'] > $compare)) {
                $this->logger->warning('Skipped Transaction: ' . json_encode($transaction, JSON_PRETTY_PRINT));
                continue;
            }
            $transactions[] = $transactionArray;
        }
        return $transactions;
    }
}
