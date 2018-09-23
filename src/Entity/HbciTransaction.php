<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HbciTransactionRepository")
 */
class HbciTransaction implements YnabCompatibleTransaction
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $bookingDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $valutaDate;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=2)
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $saldoType = 'D';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $bookingText;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description2;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $structuredDescription;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $bankCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $accountNumber;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ccIssuer;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ccIdentifier;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2, nullable=true)
     */
    private $ccValue;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ccCurrency;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ccPostingLocation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $ccPostingDate;

    /**
     * @ORM\Column(type="decimal", precision=10, scale=8, nullable=true)
     */
    private $ccExchangeRate;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $ccUnknownFloat;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ccUnknownId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $checksum;

    public function getId()
    {
        return $this->id;
    }

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeInterface $bookingDate): self
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getValutaDate(): ?\DateTimeInterface
    {
        return $this->valutaDate;
    }

    public function setValutaDate(\DateTimeInterface $valutaDate): self
    {
        $this->valutaDate = $valutaDate;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getSaldoType(): ?string
    {
        return $this->saldoType;
    }

    public function setSaldoType(string $saldoType): self
    {
        $this->saldoType = $saldoType;

        return $this;
    }

    public function getBookingText(): ?string
    {
        return $this->bookingText;
    }

    public function setBookingText(?string $bookingText): self
    {
        $this->bookingText = $bookingText;

        return $this;
    }

    public function getDescription1(): ?string
    {
        return $this->description1;
    }

    public function setDescription1(?string $description1): self
    {
        $this->description1 = $description1;

        return $this;
    }

    public function getDescription2(): ?string
    {
        return $this->description2;
    }

    public function setDescription2(?string $description2): self
    {
        $this->description2 = $description2;

        return $this;
    }

    public function getStructuredDescription()
    {
        return $this->structuredDescription;
    }

    public function setStructuredDescription($structuredDescription): self
    {
        $this->structuredDescription = $structuredDescription;

        return $this;
    }

    public function getBankCode(): ?string
    {
        return $this->bankCode;
    }

    public function setBankCode(string $bankCode): self
    {
        $this->bankCode = $bankCode;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): self
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCcIssuer(): ?string
    {
        return $this->ccIssuer;
    }

    public function setCcIssuer(?string $ccIssuer): self
    {
        $this->ccIssuer = $ccIssuer;

        return $this;
    }

    public function getCcIdentifier(): ?string
    {
        return $this->ccIdentifier;
    }

    public function setCcIdentifier(?string $ccIdentifier): self
    {
        $this->ccIdentifier = $ccIdentifier;

        return $this;
    }

    public function getCcValue(): ?string
    {
        return $this->ccValue;
    }

    public function setCcValue(?string $ccValue): self
    {
        $this->ccValue = $ccValue;

        return $this;
    }

    public function getCcCurrency(): ?string
    {
        return $this->ccCurrency;
    }

    public function setCcCurrency(?string $ccCurrency): self
    {
        $this->ccCurrency = $ccCurrency;

        return $this;
    }

    public function getCcPostingLocation(): ?string
    {
        return $this->ccPostingLocation;
    }

    public function setCcPostingLocation(?string $ccPostingLocation): self
    {
        $this->ccPostingLocation = $ccPostingLocation;

        return $this;
    }

    public function getCcPostingDate(): ?\DateTime
    {
        return $this->ccPostingDate;
    }

    public function setCcPostingDate(?\DateTime $ccPostingDate): self
    {
        $this->ccPostingDate = $ccPostingDate;

        return $this;
    }

    public function getCcExchangeRate()
    {
        return $this->ccExchangeRate;
    }

    public function setCcExchangeRate($ccExchangeRate): self
    {
        $this->ccExchangeRate = $ccExchangeRate;

        return $this;
    }

    public function getCcUnknownFloat(): ?float
    {
        return $this->ccUnknownFloat;
    }

    public function setCcUnknownFloat(?float $ccUnknownFloat): self
    {
        $this->ccUnknownFloat = $ccUnknownFloat;

        return $this;
    }

    public function getCcUnknownId(): ?int
    {
        return $this->ccUnknownId;
    }

    public function setCcUnknownId(?int $ccUnknownId): self
    {
        $this->ccUnknownId = $ccUnknownId;

        return $this;
    }

    public function getChecksum(): ?string
    {
        if ($this->checksum === null) {
            $this->calculateChecksum();
        }
        return $this->checksum;
    }

    public function calculateChecksum()
    {
        /** @noinspection PhpUndefinedClassInspection */
        $cVars = array_keys(get_class_vars(__CLASS__));
        $oVars = get_object_vars($this);
        $chechsumArray = [];
        foreach ($cVars as $var) {
            $chechsumArray[$var] = $oVars[$var];
        }
        $this->checksum = hash('sha256', json_encode($chechsumArray));
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->getValutaDate();
    }

    public function getDescription(): string
    {
        return
            'Location: ' . ucwords(strtolower($this->getCcPostingLocation())) . ', ' .
            'CC-Id: ' . ucwords(strtolower($this->getCcIssuer())) . '-' . preg_replace('/(\d{1,4})$/', '-\1',
                $this->getCcIdentifier()) . ', ' .
            'Value: ' . $this->getCcValue() . ' ' . $this->getCcCurrency() . ', ' .
            (!empty($this->getCcExchangeRate()) ? 'Exchange-Rate: ' . $this->getCcExchangeRate() . ', ' : '') .
            'UnknownFloat: ' . number_format($this->getCcUnknownFloat(), 2, ',', '') . ', ' .
            'UnknownId: ' . $this->getCcUnknownId() . ', ' .
            '';

    }

    public function getPayer(): string
    {
        return $this->getName();
    }

    public function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }

}
