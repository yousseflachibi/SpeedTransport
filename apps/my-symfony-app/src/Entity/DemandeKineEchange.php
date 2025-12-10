<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeKineEchangeRepository")
 * @ORM\Table(name="demande_kine_echange")
 */
class DemandeKineEchange
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $demandeId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $type; // 'whatsapp' ou 'appel'

    /**
     * @ORM\Column(type="text")
     */
    private $commentaire;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateEchange;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $auteur;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemandeId(): ?int
    {
        return $this->demandeId;
    }

    public function setDemandeId(int $demandeId): self
    {
        $this->demandeId = $demandeId;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDateEchange(): ?\DateTimeInterface
    {
        return $this->dateEchange;
    }

    public function setDateEchange(\DateTimeInterface $dateEchange): self
    {
        $this->dateEchange = $dateEchange;
        return $this;
    }

    public function getAuteur(): ?string
    {
        return $this->auteur;
    }

    public function setAuteur(?string $auteur): self
    {
        $this->auteur = $auteur;
        return $this;
    }
}
