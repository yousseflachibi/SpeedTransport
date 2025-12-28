<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DemandeKineSeanceRepository;

/**
 * @ORM\Entity(repositoryClass=DemandeKineSeanceRepository::class)
 * @ORM\Table(name="demande_kine_seance")
 */
class DemandeKineSeance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=DemandeKine::class)
     * @ORM\JoinColumn(name="demande_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $demande;

    /** @ORM\Column(type="datetime") */
    private $dateSeance;

    /** @ORM\Column(type="text", nullable=true) */
    private $commentaire;

    /** @ORM\Column(type="integer", nullable=true) */
    private $rating;

    /** @ORM\Column(type="datetime") */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->dateSeance = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemande(): ?DemandeKine
    {
        return $this->demande;
    }

    public function setDemande(DemandeKine $demande): self
    {
        $this->demande = $demande;
        return $this;
    }

    public function getDateSeance(): ?\DateTimeInterface
    {
        return $this->dateSeance;
    }

    public function setDateSeance(\DateTimeInterface $dateSeance): self
    {
        $this->dateSeance = $dateSeance;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;
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
}
