<?php

namespace App\Entity;

use App\Repository\DesignRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DesignRepository::class)]
class Design
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
     private ?string $couleurPoints = '#000000';

    #[ORM\Column(length: 10)]
    private ?string $couleurFond = '#ffffff';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $formeYeux = null;

    #[ORM\Column(nullable: true)]
     private ?int $taille = 300;

    #[ORM\Column(nullable: true)]
    private ?int $marge = 10;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCouleurPoints(): ?string
    {
        return $this->couleurPoints;
    }

    public function setCouleurPoints(string $couleurPoints): static
    {
        $this->couleurPoints = $couleurPoints;

        return $this;
    }

    public function getCouleurFond(): ?string
    {
        return $this->couleurFond;
    }

    public function setCouleurFond(string $couleurFond): static
    {
        $this->couleurFond = $couleurFond;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }

    public function getFormeYeux(): ?string
    {
        return $this->formeYeux;
    }

    public function setFormeYeux(?string $formeYeux): static
    {
        $this->formeYeux = $formeYeux;

        return $this;
    }

    public function getTaille(): ?int { return $this->taille; }
    public function setTaille(?int $taille): static { $this->taille = $taille; return $this; }

    public function getMarge(): ?int { return $this->marge; }
    public function setMarge(?int $marge): static { $this->marge = $marge; return $this; }


    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }
}
