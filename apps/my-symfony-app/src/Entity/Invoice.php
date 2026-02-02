<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

/**
 * @ORM\Entity
 * @ORM\Table(name="invoice")
 */
class Invoice
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $agent;

    /** @ORM\Column(type="string", length=7) */
    private $month; // format YYYY-MM

    /** @ORM\Column(type="decimal", precision=10, scale=2) */
    private $amount;

    /** @ORM\Column(type="boolean") */
    private $paid = false;

    /** @ORM\Column(type="text", nullable=true) */
    private $details;

    /** @ORM\Column(type="datetime") */
    private $createdAt;

    // Comptages mois courant
    /** @ORM\Column(type="integer", options={"default":0}) */
    private $en_cours_current = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $en_attente_current = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $accepted_current = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $rejected_current = 0;

    // Comptages mois précédents
    /** @ORM\Column(type="integer", options={"default":0}) */
    private $en_cours_previous = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $en_attente_previous = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $accepted_previous = 0;

    /** @ORM\Column(type="integer", options={"default":0}) */
    private $rejected_previous = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?User
    {
        return $this->agent;
    }

    public function setAgent(User $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function setMonth(string $month): self
    {
        $this->month = $month;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount($amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function isPaid(): bool
    {
        return (bool)$this->paid;
    }

    public function setPaid(bool $paid): self
    {
        $this->paid = $paid;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // Getters/Setters pour mois courant
    public function getEnCoursCurrent(): int
    {
        return $this->en_cours_current;
    }

    public function setEnCoursCurrent(int $en_cours_current): self
    {
        $this->en_cours_current = $en_cours_current;
        return $this;
    }

    public function getEnAttenteCurrent(): int
    {
        return $this->en_attente_current;
    }

    public function setEnAttenteCurrent(int $en_attente_current): self
    {
        $this->en_attente_current = $en_attente_current;
        return $this;
    }

    public function getAcceptedCurrent(): int
    {
        return $this->accepted_current;
    }

    public function setAcceptedCurrent(int $accepted_current): self
    {
        $this->accepted_current = $accepted_current;
        return $this;
    }

    public function getRejectedCurrent(): int
    {
        return $this->rejected_current;
    }

    public function setRejectedCurrent(int $rejected_current): self
    {
        $this->rejected_current = $rejected_current;
        return $this;
    }

    // Getters/Setters pour mois précédents
    public function getEnCoursPrevious(): int
    {
        return $this->en_cours_previous;
    }

    public function setEnCoursPrevious(int $en_cours_previous): self
    {
        $this->en_cours_previous = $en_cours_previous;
        return $this;
    }

    public function getEnAttentePrevious(): int
    {
        return $this->en_attente_previous;
    }

    public function setEnAttentePrevious(int $en_attente_previous): self
    {
        $this->en_attente_previous = $en_attente_previous;
        return $this;
    }

    public function getAcceptedPrevious(): int
    {
        return $this->accepted_previous;
    }

    public function setAcceptedPrevious(int $accepted_previous): self
    {
        $this->accepted_previous = $accepted_previous;
        return $this;
    }

    public function getRejectedPrevious(): int
    {
        return $this->rejected_previous;
    }

    public function setRejectedPrevious(int $rejected_previous): self
    {
        $this->rejected_previous = $rejected_previous;
        return $this;
    }
}
