<?php

namespace App\Boon\Transformer;

use App\Entity\BoonTransaction;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class BoonTransactionArrayToBoonTransactionTransformer
{
    private const FIELDS = [
        'id',
        'type',
        'currency',
        'amount',
        'merchant',
        'time',
    ];
    public function transform($transaction)
    {
        /** @var array $transaction */
        $boonTransaction = new BoonTransaction();
        $transactionKeys = array_keys($transaction);
        $boonTransactionMethods = get_class_methods(BoonTransaction::class);
        foreach ($transactionKeys as $key) {
            $value = $transaction[$key];
            $setterName = 'set' . ucwords($key);
            if (\in_array($setterName, $boonTransactionMethods, true)) {
                $boonTransaction->$setterName($value);
            }
        }

        $boonTransaction->calculateChecksum();

        return $boonTransaction;
    }

    public function reverseTransform($boonTransaction)
    {
        /** @var BoonTransaction $boonTransaction */
        $transaction = [];
        $boonTransactionMethods = get_class_methods(BoonTransaction::class);
        foreach ($boonTransactionMethods as $method) {
            if (preg_match('/^get(?P<fieldName>.+)$/', $method, $matches)) {
                $getterName = $matches[0];
                $value = $boonTransaction->$getterName();
                if (\in_array($matches['fieldName'], self::FIELDS, true)) {
                    $transaction[$matches['fieldName']] = $value;
                }
            }
        }

        return $transaction;
    }
}
