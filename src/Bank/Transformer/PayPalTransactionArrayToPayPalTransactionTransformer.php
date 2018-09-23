<?php

namespace App\Bank\Transformer;

use App\Entity\PayPalTransaction;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class PayPalTransactionArrayToPayPalTransactionTransformer
{
    private const FIELDS = [
        'date',
        'amount',
        'description',
        'payer',
        'type',
    ];
    public function transform($transaction)
    {
        /** @var array $transaction */
        $payPalTransaction = new PayPalTransaction();
        $transactionKeys = array_keys($transaction);
        $paypalTransactionMethods = get_class_methods(PayPalTransaction::class);
        foreach ($transactionKeys as $key) {
            $value = $transaction[$key];
            $setterName = 'set' . ucwords($key);
            if (\in_array($setterName, $paypalTransactionMethods, true)) {
                $payPalTransaction->$setterName($value);
            }
        }

        $payPalTransaction->calculateChecksum();

        return $payPalTransaction;
    }

    public function reverseTransform($payPalTransaction)
    {
        /** @var PayPalTransaction $payPalTransaction */
        $transaction = [];
        $paypalTransactionMethods = get_class_methods(PayPalTransaction::class);
        foreach ($paypalTransactionMethods as $method) {
            if (preg_match('/^get(?P<fieldName>.+)$/', $method, $matches)) {
                $getterName = $matches[0];
                $value = $payPalTransaction->$getterName();
                if (\in_array($matches['fieldName'], self::FIELDS, true)) {
                    $transaction[$matches['fieldName']] = $value;
                }
            }
        }

        return $transaction;
    }
}
