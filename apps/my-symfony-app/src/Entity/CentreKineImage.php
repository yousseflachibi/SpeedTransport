<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="centre_kine_image")
 */
class CentreKineImage
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=CentreKine::class)
     * @ORM\JoinColumn(name="centre_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $centre;

    /** @ORM\Column(type="string", length=255) */
    private $url;

    /** @ORM\Column(type="datetime") */
    private $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getCentre(): ?CentreKine { return $this->centre; }
    public function setCentre(CentreKine $centre): self { $this->centre = $centre; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(string $url): self { $this->url = $url; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $dt): self { $this->createdAt = $dt; return $this; }
}
