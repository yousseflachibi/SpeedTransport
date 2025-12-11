<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity
 * @ORM\Table(name="centre_kine")
 */
class CentreKine
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /** @ORM\Column(type="string", length=255) */
    private $nom;

    /** @ORM\Column(type="text", nullable=true) */
    private $adresse;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $imagePrincipale;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $mapX;

    /** @ORM\Column(type="string", length=50, nullable=true) */
    private $mapY;

    /** @ORM\Column(type="datetime") */
    private $dateInscription;

    /**
     * @ORM\ManyToOne(targetEntity=ZoneKine::class)
     * @ORM\JoinColumn(name="zone_id", referencedColumnName="id", nullable=true)
     */
    private $zone;

    /**
     * @ORM\ManyToMany(targetEntity=ServiceKine::class)
     * @ORM\JoinTable(name="centre_kine_service",
     *      joinColumns={@ORM\JoinColumn(name="centre_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="service_id", referencedColumnName="id")}
     * )
     */
    private $services;

    public function __construct() { $this->services = new ArrayCollection(); }
    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $adresse): self { $this->adresse = $adresse; return $this; }
    public function getImagePrincipale(): ?string { return $this->imagePrincipale; }
    public function setImagePrincipale(?string $imagePrincipale): self { $this->imagePrincipale = $imagePrincipale; return $this; }
    public function getMapX(): ?string { return $this->mapX; }
    public function setMapX(?string $mapX): self { $this->mapX = $mapX; return $this; }
    public function getMapY(): ?string { return $this->mapY; }
    public function setMapY(?string $mapY): self { $this->mapY = $mapY; return $this; }
    public function getDateInscription(): ?\DateTimeInterface { return $this->dateInscription; }
    public function setDateInscription(\DateTimeInterface $dateInscription): self { $this->dateInscription = $dateInscription; return $this; }

    /**
     * @return Collection|ServiceKine[]
     */
    public function getServices(): Collection { return $this->services; }
    public function addService(ServiceKine $service): self { if(!$this->services->contains($service)) { $this->services->add($service); } return $this; }
    public function removeService(ServiceKine $service): self { $this->services->removeElement($service); return $this; }

    public function getZone(): ?ZoneKine { return $this->zone; }
    public function setZone(?ZoneKine $zone): self { $this->zone = $zone; return $this; }
}
