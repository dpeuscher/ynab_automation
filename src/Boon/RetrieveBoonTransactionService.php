<?php

namespace App\Boon;

use App\Entity\BoonTransaction;
use GuzzleHttp\Client;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class RetrieveBoonTransactionService
{
    protected const URL_BASE = 'https://sps2c.wirecard.com/rest-api';
    protected const URL_VERSION = '/v14/boon';
    protected const URL_USER = '/user/%s';
    protected const URL_TRANSACTIONS_ENTITY = '/transactions';
    protected const URL_ADDITIONAL_PARAMS = [
        'selectBy'  => 'transaction_date',
        'aliasType' => 'msisdn',
    ];

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * RetrieveBoonTransactionService constructor.
     *
     * @param string $username
     * @param string $password
     */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return array
     * @throws \Exception
     */
    public function getTransactions(\DateTime $from, \DateTime $to): array
    {
        $client = new Client();
        $result = $client->get($this->getUrl() . '&from=' . $from->format('Y-m-d\TH:i:s') . '&to=' . $to->format('Y-m-d\TH:i:s'),
            [
                'auth' => [$this->username, $this->password, 'basic'],
            ]);
        $body = $result->getBody()->getContents();
        $xml = new \SimpleXMLElement($body);
        $xml->registerXPathNamespace('w', 'http://wirecardbank.com/rest-api');
        $xmlTransactions = $xml->xpath('//w:transactions/w:transaction');
        $transactions = [];
        if ($xmlTransactions) {
            foreach ($xmlTransactions as $xmlTransaction) {
                $xmlTransaction->registerXPathNamespace('w', 'http://wirecardbank.com/rest-api');
                $id = (string)$xmlTransaction->xpath('./w:transaction-id')[0];
                $type = (string)$xmlTransaction->xpath('./w:transaction-type')[0];
                $currency = (string)$xmlTransaction->xpath('./w:amount/@currency')[0];
                $amount = (float)$xmlTransaction->xpath('./w:amount')[0];
                switch ($type) {
                    case BoonTransaction::TRANSACTION_TYPE_USAGE:
                        $amount *= -1;
                        /** @noinspection UnnecessaryParenthesesInspection */
                        $merchant = (string)(($xmlTransaction->xpath('./w:transaction-details/w:merchant') ?? [])[0] ?? '');
                        /** @noinspection UnnecessaryParenthesesInspection */
                        $merchantCity = (string)(($xmlTransaction->xpath('./w:transaction-details/w:merchant-city') ?? [])[0] ?? '');
                        /** @noinspection UnnecessaryParenthesesInspection */
                        $merchantCountry = (string)(($xmlTransaction->xpath('./w:transaction-details/w:merchant-country') ?? [])[0] ?? '');
                        $merchantDescription = trim($merchantCity . ' ' . $merchantCountry) ?: '';
                        break;
                    case BoonTransaction::TRANSACTION_TYPE_REFILL:
                        /** @noinspection UnnecessaryParenthesesInspection */
                        $refillSource = (string)(($xmlTransaction->xpath('./w:transaction-details/w:credit-card/w:alias') ?? [])[0] ?? '');
                        $merchant = trim($refillSource) ?: '';
                        $merchantDescription = '';
                        break;
                    case BoonTransaction::TRANSACTION_TYPE_COUPON:
                        $merchant = 'Boon Bonus';
                        $merchantDescription = 'Coupon';
                        break;
                    default:
                        throw new \RuntimeException('Unknown Transaction Type: ' . $type);
                }
                /** @noinspection UnnecessaryParenthesesInspection */
                $date = (string)(($xmlTransaction->xpath('./w:transaction-date') ?? [])[0] ?? '');
                $dateTime = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $date);

                $transactions[] = [
                    'id'          => $id,
                    'type'        => $type,
                    'currency'    => $currency,
                    'amount'      => $amount,
                    'payer'       => $merchant,
                    'date'        => $dateTime,
                    'description' => $merchantDescription,
                ];
            }
        }
        return $transactions;
    }

    private function getUrl(): string
    {
        return self::URL_BASE . self::URL_VERSION . sprintf(self::URL_USER,
                $this->username) . self::URL_TRANSACTIONS_ENTITY . '?' . $this->http_build_str(self::URL_ADDITIONAL_PARAMS);
    }

    private function http_build_str(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = urlencode($key) . '=' . urlencode($value);
        }
        return implode('&', $parts);
    }
}
