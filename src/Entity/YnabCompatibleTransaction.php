<?php

namespace App\Entity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HbciTransactionRepository")
 */
interface YnabCompatibleTransaction
{
    public function getAmount(): float;

    public function getDate(): \DateTimeInterface;

    public function getDescription(): string;

    public function getPayer(): string;

    public function __toString(): string;
}