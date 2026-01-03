<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: "payments")]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 36, unique: true)]
    private ?string $paymentId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = 'USD';

    #[ORM\Column(length: 50)]
    private ?string $status = 'PENDING'; // PENDING, COMPLETED, FAILED, CANCELLED

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paypalTransactionId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cardBrand = null; // VISA, MASTERCARD, AMEX, etc.

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $cardLastFour = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\OneToOne(targetEntity: Ticket::class, mappedBy: "payment", cascade: ["persist"])]
    private ?Ticket $ticket = null;

    public function __construct()
    {
        $this->paymentId = uniqid('pay_', true);
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'PENDING';
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }

    public function getPaymentId(): ?string { return $this->paymentId; }
    public function setPaymentId(string $paymentId): static { $this->paymentId = $paymentId; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getPaypalTransactionId(): ?string { return $this->paypalTransactionId; }
    public function setPaypalTransactionId(?string $paypalTransactionId): static { $this->paypalTransactionId = $paypalTransactionId; return $this; }

    public function getCardBrand(): ?string { return $this->cardBrand; }
    public function setCardBrand(?string $cardBrand): static { $this->cardBrand = $cardBrand; return $this; }

    public function getCardLastFour(): ?string { return $this->cardLastFour; }
    public function setCardLastFour(?string $cardLastFour): static { $this->cardLastFour = $cardLastFour; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $completedAt): static { $this->completedAt = $completedAt; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }

    public function getTicket(): ?Ticket { return $this->ticket; }
    public function setTicket(?Ticket $ticket): static { $this->ticket = $ticket; return $this; }
}

