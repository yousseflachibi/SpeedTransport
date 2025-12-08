<?php

namespace App\Entity;

use App\Repository\ServiceKineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ServiceKineRepository::class)
 * @ORM\Table(name="service_kine")
 */
class ServiceKine
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity=CategorieServiceKine::class)
     * @ORM\JoinColumn(name="categorie_id", referencedColumnName="id", nullable=false)
     */
    private $categorie;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $price;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategorie(): ?CategorieServiceKine
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieServiceKine $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }
}
