<?php

namespace App\Entity;

use App\Repository\ScanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScanRepository::class)]
class Scan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateHeure = null;

    #[ORM\Column(length: 45)]
    private ?string $ipAdresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $appareil = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $navigateur = null;

    #[ORM\ManyToOne(inversedBy: 'scans')]
    private ?QrCode $qrCode = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateHeure(): ?\DateTime
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTime $dateHeure): static
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getIpAdresse(): ?string
    {
        return $this->ipAdresse;
    }

    public function setIpAdresse(string $ipAdresse): static
    {
        $this->ipAdresse = $ipAdresse;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getAppareil(): ?string
    {
        return $this->appareil;
    }

    public function setAppareil(?string $appareil): static
    {
        $this->appareil = $appareil;

        return $this;
    }

    public function getNavigateur(): ?string
    {
        return $this->navigateur;
    }

    public function setNavigateur(?string $navigateur): static
    {
        $this->navigateur = $navigateur;

        return $this;
    }

    public function getQrCode(): ?QrCode
    {
        return $this->qrCode;
    }

    public function setQrCode(?QrCode $qrCode): static
    {
        $this->qrCode = $qrCode;

        return $this;
    }
}
