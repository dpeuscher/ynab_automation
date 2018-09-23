<?php

namespace App\Bank\Transformer;

use App\Bank\BankTransaction;
use App\Entity\HbciTransaction;
use Fhp\Model\StatementOfAccount\Transaction;

/**
 * @category  ynab_automation
 * @copyright Copyright (c) 2018 Dominik Peuscher
 */
class TransactionToHbciTransactionTransformer
{
    public function transform($transaction): HbciTransaction
    {
        /** @var Transaction $transaction */

        $hbciTransaction = new HbciTransaction();
        $transactionMethods = get_class_methods(\get_class($transaction));
        $hbciMethods = get_class_methods(HbciTransaction::class);
        foreach ($transactionMethods as $method) {
            if (preg_match('/^get(?P<fieldName>.+)$/', $method, $matches)) {
                $getterName = $matches[0];
                $value = $transaction->$getterName();
                $setterName = 'set' . ucwords($matches['fieldName']);
                if (\in_array($setterName, $hbciMethods, true)) {
                    $hbciTransaction->$setterName($value);
                }
            }
        }

        $hbciTransaction->calculateChecksum();

        return $hbciTransaction;
    }

    public function reverseTransform($hbciTransaction): BankTransaction
    {
        /** @var HbciTransaction $hbciTransaction */

        $transaction = new BankTransaction();
        // TODO Detect transaction type
        $transactionMethods = get_class_methods(BankTransaction::class);
        $hbciMethods = get_class_methods(HbciTransaction::class);
        foreach ($hbciMethods as $method) {
            if (preg_match('/^get(?P<fieldName>.+)$/', $method, $matches)) {
                $getterName = $matches[0];
                $value = $hbciTransaction->$getterName();
                $setterName = 'set' . ucwords($matches['fieldName']);
                if (\in_array($setterName, $transactionMethods, true)) {
                    $transaction->$setterName($value);
                }
            }
        }

        return $transaction;
    }
}
