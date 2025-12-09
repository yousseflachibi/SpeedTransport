<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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
}
