<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\Table(name: "tickets")]
class Ticket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 36, unique: true)]
    private ?string $ticketKey = null; // Unique ticket key

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToOne(targetEntity: Payment::class, inversedBy: "ticket")]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $eventName = null;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $ticketType = 'GENERAL'; // GENERAL, VIP, STUDENT, etc.

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $status = 'ACTIVE'; // ACTIVE, USED, EXPIRED, CANCELLED

    #[ORM\Column(type: "integer", options: ["default" => 1])]
    private int $quantity = 1;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $qrCode = null;

    public function __construct()
    {
        $this->ticketKey = uniqid('ticket_', true);
        $this->issuedAt = new \DateTimeImmutable();
        $this->status = 'ACTIVE';
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }

    public function getTicketKey(): ?string { return $this->ticketKey; }
    public function setTicketKey(string $ticketKey): static { $this->ticketKey = $ticketKey; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPayment(): ?Payment { return $this->payment; }
    public function setPayment(?Payment $payment): static { $this->payment = $payment; return $this; }

    public function getEventName(): ?string { return $this->eventName; }
    public function setEventName(?string $eventName): static { $this->eventName = $eventName; return $this; }

    public function getTicketType(): ?string { return $this->ticketType; }
    public function setTicketType(string $ticketType): static { $this->ticketType = $ticketType; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }

    public function getIssuedAt(): \DateTimeImmutable { return $this->issuedAt; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(0, $quantity); return $this; }

    public function decrementQuantity(int $amount = 1): static 
    { 
        $this->quantity = max(0, $this->quantity - $amount); 
        return $this; 
    }

    public function incrementQuantity(int $amount = 1): static 
    { 
        $this->quantity += $amount; 
        return $this; 
    }

    public function isSoldOut(): bool { return $this->quantity <= 0; }

    public function getQrCode(): ?string { return $this->qrCode; }
    public function setQrCode(?string $qrCode): static { $this->qrCode = $qrCode; return $this; }
}
