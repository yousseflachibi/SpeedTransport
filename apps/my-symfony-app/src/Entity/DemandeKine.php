<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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
    private $nomAgent;

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

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateSuivi;

    /**
     * @ORM\ManyToMany(targetEntity=ServiceKine::class)
     * @ORM\JoinTable(name="demande_kine_service",
     *      joinColumns={@ORM\JoinColumn(name="demande_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="service_id", referencedColumnName="id")}
     * )
     */
    private $services;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $centresAssignes = [];

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $idContactUs;

    // Relation vers l'utilisateur créateur (ajoutée ultérieurement)
    // private $agentUser;

    public function __construct()
    {
        $this->services = new ArrayCollection();
    }

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

    public function getNomAgent(): ?string
    {
        return $this->nomAgent;
    }

    public function setNomAgent(?string $nomAgent): self
    {
        $this->nomAgent = $nomAgent;
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

    public function getDateSuivi(): ?\DateTimeInterface
    {
        return $this->dateSuivi;
    }

    public function setDateSuivi(?\DateTimeInterface $dateSuivi): self
    {
        $this->dateSuivi = $dateSuivi;
        return $this;
    }

    /**
     * @return Collection|ServiceKine[]
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(ServiceKine $service): self
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }
        return $this;
    }

    public function removeService(ServiceKine $service): self
    {
        $this->services->removeElement($service);
        return $this;
    }

    public function getCentresAssignes(): ?array
    {
        return $this->centresAssignes ?? [];
    }

    public function setCentresAssignes(?array $centresAssignes): self
    {
        $this->centresAssignes = $centresAssignes;
        return $this;
    }

    public function getIdContactUs(): ?int
    {
        return $this->idContactUs;
    }

    public function setIdContactUs(?int $idContactUs): self
    {
        $this->idContactUs = $idContactUs;
        return $this;
    }

    // public function getAgentUser(): ?\\App\\Entity\\User { return $this->agentUser; }
    // public function setAgentUser(?\\App\\Entity\\User $agentUser): self { $this->agentUser = $agentUser; return $this; }
}
