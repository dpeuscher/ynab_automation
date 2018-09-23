<?php

namespace App\Ynab;

use App\Entity\YnabCompatibleTransaction;
use YNAB\Model\ScheduledTransactionDetail;
use YNAB\Model\SubTransaction;
use YNAB\Model\TransactionDetail;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class TransactionAnalyser
{
    /**
     * @param TransactionDetail[] $ynabTransactions
     * @param ScheduledTransactionDetail[] $ynabScheduledTransactions
     * @return array[][]
     */
    public function buildPriceArray(array $ynabTransactions, $ynabScheduledTransactions): array
    {
        $multiTransactions = [];
        $prices = [];
        $subPrices = [];
        $scheduledPrices = [];
        foreach ($ynabTransactions as $transaction) {
            if (!isset($prices[(string)abs($transaction->getAmount() / 1000)])) {
                $prices[(string)abs($transaction->getAmount() / 1000)] = [];
            }
            $prices[(string)abs($transaction->getAmount() / 1000)][] = $transaction;
            if ($transaction->getSubtransactions()) {
                $multiTransactions[$transaction->getId()] = $transaction;
                foreach ($transaction->getSubtransactions() as $subTransaction) {
                    if (!isset($subPrices[(string)abs($subTransaction->getAmount() / 1000)])) {
                        $subPrices[(string)abs($subTransaction->getAmount() / 1000)] = [];
                    }
                    $subPrices[(string)abs($subTransaction->getAmount() / 1000)][] = $subTransaction;
                }
            }
        }
        foreach ($ynabScheduledTransactions as $scheduledTransaction) {
            if (!isset($scheduledPrices[(string)abs($scheduledTransaction->getAmount() / 1000)])) {
                $scheduledPrices[(string)abs($scheduledTransaction->getAmount() / 1000)] = [];
            }
            $scheduledPrices[(string)abs($scheduledTransaction->getAmount() / 1000)][] = $scheduledTransaction;
            if ($scheduledTransaction->getSubtransactions()) {
                $multiTransactions[$scheduledTransaction->getId()] = $scheduledTransaction;
                foreach ($scheduledTransaction->getSubtransactions() as $subTransaction) {
                    if (!isset($subPrices[(string)abs($subTransaction->getAmount() / 1000)])) {
                        $subPrices[(string)abs($subTransaction->getAmount() / 1000)] = [];
                    }
                    $subPrices[(string)abs($subTransaction->getAmount() / 1000)][] = $subTransaction;
                }
            }
        }
        return [
            'prices'            => $prices,
            'subPrices'         => $subPrices,
            'scheduledPrices'   => $scheduledPrices,
            'multiTransactions' => $multiTransactions,
        ];
    }

    /**
     * @param YnabCompatibleTransaction[] $amountTransactions
     * @param TransactionDetail[] $ynabTransactions
     * @param ScheduledTransactionDetail[] $ynabScheduledTransactions
     * @return string[]
     * @throws \Exception
     */
    public function buildNamePayeeMapByClosestMatch(
        array $amountTransactions,
        array $ynabTransactions,
        array $ynabScheduledTransactions
    ): array {
        $namePayeeMap = [];
        /**
         * @var TransactionDetail[][] $prices
         * @var SubTransaction[][] $subPrices
         * @var ScheduledTransactionDetail[][] $scheduledPrices
         * @var TransactionDetail[] $multiTransactions
         */
        ['prices' => $prices, 'subPrices' => $subPrices, 'scheduledPrices' => $scheduledPrices, 'multiTransactions' => $multiTransactions] = $this->buildPriceArray($ynabTransactions,
            $ynabScheduledTransactions);

        foreach ($amountTransactions as $transaction) {
            $smallestDiff = null;
            $diffs = [];
            $matchCount = \count($prices[(string)abs($transaction->getAmount())] ?? [])
                + \count($subPrices[(string)abs($transaction->getAmount())] ?? [])
                + \count($scheduledPrices[(string)abs($transaction->getAmount())] ?? []);
            if ($matchCount === 1) {
                if (isset($prices[(string)abs($transaction->getAmount())]) && !empty($prices[(string)abs($transaction->getAmount())])) {
                    $smallestDiff = current($prices[(string)abs($transaction->getAmount())]);
                    unset($prices[(string)abs($transaction->getAmount())]);
                } elseif (isset($subPrices[(string)abs($transaction->getAmount())]) && !empty($subPrices[(string)abs($transaction->getAmount())])) {
                    $smallestDiff = current($subPrices[(string)abs($transaction->getAmount())]);
                    unset($subPrices[(string)abs($transaction->getAmount())]);
                } elseif (isset($scheduledPrices[(string)abs($transaction->getAmount())]) && !empty($scheduledPrices[(string)abs($transaction->getAmount())])) {
                    $smallestDiff = current($scheduledPrices[(string)abs($transaction->getAmount())]);
                    unset($scheduledPrices[(string)abs($transaction->getAmount())]);
                } else {
                    throw new \RuntimeException('Should not happen ' . __FILE__ . ':' . __LINE__);
                }
            } elseif ($matchCount > 1) {
                $i = 0;
                $refs = ['prices' => [], 'subPrices' => [], 'scheduledPrices' => []];
                if (isset($prices[(string)abs($transaction->getAmount())]) && !empty($prices[(string)abs($transaction->getAmount())])) {
                    foreach ($prices[(string)abs($transaction->getAmount())] as $nr => $ynabTrans) {
                        $diffs[$i] = $ynabTrans->getDate()->diff($transaction->getDate())->days;
                        $refs['prices'][$i] = $nr;
                        $i++;
                    }
                }
                if (isset($subPrices[(string)abs($transaction->getAmount())]) && !empty($subPrices[(string)abs($transaction->getAmount())])) {
                    foreach ($subPrices[(string)abs($transaction->getAmount())] as $nr => $subPriceTrans) {
                        $diffs[$i] = $multiTransactions[$subPriceTrans->getTransactionId()]->getDate()->diff($transaction->getDate())->days;
                        $refs['subPrices'][$i] = $nr;
                        $i++;
                    }
                }
                if (isset($scheduledPrices[(string)abs($transaction->getAmount())]) && !empty($scheduledPrices[(string)abs($transaction->getAmount())])) {
                    /**
                     * @var int $nr
                     * @var ScheduledTransactionDetail $scheduledTrans
                     */
                    foreach ($scheduledPrices[(string)abs($transaction->getAmount())] as $nr => $scheduledTrans) {
                        $diffs[$i] = $scheduledTrans->getDateNext()->diff($transaction->getDate())->days;
                        $refs['scheduledPrices'][$i] = $nr;
                        $i++;
                    }
                }
                asort($diffs);
                $match = key($diffs);
                if (isset($refs['prices'][$match])) {
                    $smallestDiff = $prices[(string)abs($transaction->getAmount())][$refs['prices'][$match]];
                    unset($prices[(string)abs($transaction->getAmount())][$refs['prices'][$match]]);
                } elseif (isset($refs['subPrices'][$match])) {
                    $smallestDiff = $subPrices[(string)abs($transaction->getAmount())][$refs['subPrices'][$match]];
                    unset($subPrices[(string)abs($transaction->getAmount())][$refs['subPrices'][$match]]);
                } elseif (isset($refs['scheduledPrices'][$match])) {
                    $smallestDiff = $scheduledPrices[(string)abs($transaction->getAmount())][$refs['scheduledPrices'][$match]];
                    unset($scheduledPrices[(string)abs($transaction->getAmount())][$refs['scheduledPrices'][$match]]);
                } else {
                    throw new \RuntimeException('Should not happen ' . __FILE__ . ':' . __LINE__);
                }
            }
            if ($smallestDiff !== null) {
                $namePayeeMap[$transaction->getPayer()] = $smallestDiff->getPayeeId();
            }
        }
        return $namePayeeMap;
    }

    /**
     * @param YnabCompatibleTransaction[] $amountTransactions
     * @param TransactionDetail[] $ynabTransactions
     * @param ScheduledTransactionDetail[] $ynabScheduledTransactions
     * @return YnabCompatibleTransaction[]
     * @throws \Exception
     */
    public function findNewYnabTransactions(
        array $amountTransactions,
        array $ynabTransactions,
        array $ynabScheduledTransactions
    ): array {
        /**
         * @var TransactionDetail[][] $prices
         * @var SubTransaction[][] $subPrices
         * @var ScheduledTransactionDetail[][] $scheduledPrices
         * @var TransactionDetail[] $multiTransactions
         */
        ['prices' => $prices, 'subPrices' => $subPrices, 'scheduledPrices' => $scheduledPrices, 'multiTransactions' => $multiTransactions] = $this->buildPriceArray($ynabTransactions,
            $ynabScheduledTransactions);

        $newYnabTransactions = [];
        foreach ($amountTransactions as $transaction) {
            $diffs = [];

            $matchCount = \count($prices[(string)abs($transaction->getAmount())] ?? [])
                + \count($subPrices[(string)abs($transaction->getAmount())] ?? [])
                + \count($scheduledPrices[(string)abs($transaction->getAmount())] ?? []);
            if ($matchCount === 1) {
                if (isset($prices[(string)abs($transaction->getAmount())])) {
                    unset($prices[(string)abs($transaction->getAmount())]);
                } elseif (isset($subPrices[(string)abs($transaction->getAmount())])) {
                    unset($subPrices[(string)abs($transaction->getAmount())]);
                } elseif (isset($scheduledPrices[(string)abs($transaction->getAmount())])) {
                    unset($scheduledPrices[(string)abs($transaction->getAmount())]);
                } else {
                    throw new \RuntimeException('Should not happen ' . __FILE__ . ':' . __LINE__);
                }
            } elseif ($matchCount > 1) {
                $i = 0;
                $refs = ['prices' => [], 'subPrices' => [], 'scheduledPrices' => []];
                if (isset($prices[(string)abs($transaction->getAmount())])) {
                    foreach ($prices[(string)abs($transaction->getAmount())] as $nr => $ynabTrans) {
                        $diffs[$i] = $ynabTrans->getDate()->diff($transaction->getDate())->days;
                        $refs['prices'][$i] = $nr;
                        $i++;
                    }
                }
                if (isset($subPrices[(string)abs($transaction->getAmount())])) {
                    foreach ($subPrices[(string)abs($transaction->getAmount())] as $nr => $ynabTrans) {
                        $diffs[$i] = $multiTransactions[$ynabTrans->getTransactionId()]->getDate()->diff($transaction->getDate())->days;
                        $refs['subPrices'][$i] = $nr;
                        $i++;
                    }
                }
                if (isset($scheduledPrices[(string)abs($transaction->getAmount())])) {
                    /**
                     * @var int $nr
                     * @var ScheduledTransactionDetail $scheduledTrans
                     */
                    foreach ($scheduledPrices[(string)abs($transaction->getAmount())] as $nr => $scheduledTrans) {
                        $diffs[$i] = $scheduledTrans->getDateNext()->diff($transaction->getDate())->days;
                        $refs['scheduledPrices'][$i] = $nr;
                        $i++;
                    }
                }
                asort($diffs);
                $match = key($diffs);
                if (isset($refs['prices'][$match])) {
                    unset($prices[(string)abs($transaction->getAmount())][$refs['prices'][$match]]);
                } elseif (isset($refs['subPrices'][$match])) {
                    unset($subPrices[(string)abs($transaction->getAmount())][$refs['subPrices'][$match]]);
                } elseif (isset($refs['scheduledPrices'][$match])) {
                    unset($scheduledPrices[(string)abs($transaction->getAmount())][$refs['scheduledPrices'][$match]]);
                } else {
                    throw new \RuntimeException('Should not happen ' . __FILE__ . ':' . __LINE__);
                }
            } else {
                $newYnabTransactions[] = $transaction;
            }
        }
        return $newYnabTransactions;
    }
}
