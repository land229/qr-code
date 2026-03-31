<?php

namespace App\Entity;

use App\Repository\QrCodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QrCodeRepository::class)]
class QrCode 
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 55)]
    private ?string $titre = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $urlCourte = null;

    #[ORM\Column(length: 2048,nullable: true)]
    private ?string $urlDestination = null;

    #[ORM\Column]
    private ?bool $estActif = null;

    #[ORM\Column]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $quotaMaxScans = null;

    #[ORM\Column]
    private ?int $compteurScans = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\ManyToOne(inversedBy: 'qRCodes')]
    private ?User $user = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Design $design = null;

    /**
     * @var Collection<int, Scan>
     */
    #[ORM\OneToMany(targetEntity: Scan::class, mappedBy: 'qrCode')]
    private Collection $scans;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'qrCode')]
    private Collection $notifications;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contenu = null;

    public function __construct()
    {
        $this->scans = new ArrayCollection();
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
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

    public function getUrlCourte(): ?string
    {
        return $this->urlCourte;
    }

    public function setUrlCourte(string $urlCourte): static
    {
        $this->urlCourte = $urlCourte;

        return $this;
    }

    public function getUrlDestination(): ?string
    {
        return $this->urlDestination;
    }

    public function setUrlDestination(string $urlDestination): static
    {
        $this->urlDestination = $urlDestination;

        return $this;
    }

    public function isEstActif(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getQuotaMaxScans(): ?int
    {
        return $this->quotaMaxScans;
    }

    public function setQuotaMaxScans(?int $quotaMaxScans): static
    {
        $this->quotaMaxScans = $quotaMaxScans;

        return $this;
    }

    public function getCompteurScans(): ?int
    {
        return $this->compteurScans;
    }

    public function setCompteurScans(int $compteurScans): static
    {
        $this->compteurScans = $compteurScans;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    // NOTE: renamed from getUtilisateur/setUtilisateur to match english naming used elsewhere
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDesign(): ?Design
    {
        return $this->design;
    }

    public function setDesign(?Design $design): static
    {
        $this->design = $design;

        return $this;
    }

    /**
     * @return Collection<int, Scan>
     */
    public function getScans(): Collection
    {
        return $this->scans;
    }

    public function addScan(Scan $scan): static
    {
        if (!$this->scans->contains($scan)) {
            $this->scans->add($scan);
            $scan->setQrCode($this);
        }

        return $this;
    }

    public function removeScan(Scan $scan): static
    {
        if ($this->scans->removeElement($scan)) {
            // set the owning side to null (unless already changed)
            if ($scan->getQrCode() === $this) {
                $scan->setQrCode(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setQrCode($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getQrCode() === $this) {
                $notification->setQrCode(null);
            }
        }

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }
}
