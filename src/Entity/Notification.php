<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column]
    private ?\DateTime $envoyeeLe = null;

    #[ORM\Column]
    private ?bool $estLue = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    private ?QrCode $qrCode = null;

    #[ORM\ManyToOne]
    private ?User $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getEnvoyeeLe(): ?\DateTime
    {
        return $this->envoyeeLe;
    }

    public function setEnvoyeeLe(\DateTime $envoyeeLe): static
    {
        $this->envoyeeLe = $envoyeeLe;

        return $this;
    }

    public function isEstLue(): ?bool
    {
        return $this->estLue;
    }

    public function setEstLue(bool $estLue): static
    {
        $this->estLue = $estLue;

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

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
