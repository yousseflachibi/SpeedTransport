<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeKineRepository")
 * @ORM\Table(name="demande_kine")
 */
class DemandeKine
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idCompte;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateDemande;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $motifKine;

    /**
     * @ORM\Column(type="integer")
     */
    private $nombreSeance;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $adresseRejete;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $traiteParNotreCote;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idVille;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idZone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nomPrenom;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $fonction;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $numeroTele;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $numeroTeleWtp;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $cin;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $email;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdCompte(): ?int
    {
        return $this->idCompte;
    }

    public function setIdCompte(?int $idCompte): self
    {
        $this->idCompte = $idCompte;
        return $this;
    }

    public function getDateDemande(): ?\DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeInterface $dateDemande): self
    {
        $this->dateDemande = $dateDemande;
        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMotifKine(): ?string
    {
        return $this->motifKine;
    }

    public function setMotifKine(?string $motifKine): self
    {
        $this->motifKine = $motifKine;
        return $this;
    }

    public function getNombreSeance(): ?int
    {
        return $this->nombreSeance;
    }

    public function setNombreSeance(int $nombreSeance): self
    {
        $this->nombreSeance = $nombreSeance;
        return $this;
    }

    public function getAdresseRejete(): ?string
    {
        return $this->adresseRejete;
    }

    public function setAdresseRejete(?string $adresseRejete): self
    {
        $this->adresseRejete = $adresseRejete;
        return $this;
    }

    public function getTraiteParNotreCote(): ?int
    {
        return $this->traiteParNotreCote;
    }

    public function setTraiteParNotreCote(?int $traiteParNotreCote): self
    {
        $this->traiteParNotreCote = $traiteParNotreCote;
        return $this;
    }

    public function getIdVille(): ?int
    {
        return $this->idVille;
    }

    public function setIdVille(?int $idVille): self
    {
        $this->idVille = $idVille;
        return $this;
    }

    public function getIdZone(): ?int
    {
        return $this->idZone;
    }

    public function setIdZone(?int $idZone): self
    {
        $this->idZone = $idZone;
        return $this;
    }

    public function getNomPrenom(): ?string
    {
        return $this->nomPrenom;
    }

    public function setNomPrenom(?string $nomPrenom): self
    {
        $this->nomPrenom = $nomPrenom;
        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): self
    {
        $this->fonction = $fonction;
        return $this;
    }

    public function getNumeroTele(): ?string
    {
        return $this->numeroTele;
    }

    public function setNumeroTele(?string $numeroTele): self
    {
        $this->numeroTele = $numeroTele;
        return $this;
    }

    public function getNumeroTeleWtp(): ?string
    {
        return $this->numeroTeleWtp;
    }

    public function setNumeroTeleWtp(?string $numeroTeleWtp): self
    {
        $this->numeroTeleWtp = $numeroTeleWtp;
        return $this;
    }

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(?string $cin): self
    {
        $this->cin = $cin;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
