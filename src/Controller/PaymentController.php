<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Ticket;
use App\Repository\UserRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/payment')]
class PaymentController extends AbstractController
{
#[Route('/available-tickets', name: 'api_payment_available_tickets', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getAvailableTickets(
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $tickets = $entityManager->getRepository(Ticket::class)->findBy(
                ['status' => 'ACTIVE'],
                ['issuedAt' => 'DESC']
            );

            $available = array_filter($tickets, function(Ticket $ticket) {
                // Filter out sold out tickets
                return !$ticket->isSoldOut() && $ticket->getQuantity() > 0;
            });

            $data = array_map(function(Ticket $ticket) {
                return [
                    'id' => $ticket->getId(),
                    'event_name' => $ticket->getEventName(),
                    'ticket_type' => $ticket->getTicketType(),
                    'price' => $ticket->getPrice(),
                    'quantity' => $ticket->getQuantity(),
                    'sold_out' => $ticket->isSoldOut(),
                    'expires_at' => $ticket->getExpiresAt()?->format('Y-m-d H:i:s'),
                ];
            }, $available);

            return new JsonResponse([
                'success' => true,
                'count' => count($data),
                'tickets' => $data
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error fetching tickets: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/process', name: 'api_payment_process', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function processPayment(
        Request $request,
        StripeService $stripeService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            // If ticket_id provided, validate ticket exists
            $existingTicket = null;
            $requestedQuantity = (int)($data['quantity'] ?? 1);
            if (!empty($data['ticket_id'])) {
                $existingTicket = $entityManager->getRepository(Ticket::class)->find($data['ticket_id']);
                if (!$existingTicket) {
                    return new JsonResponse(['error' => 'Ticket not found'], 404);
                }
                // Use ticket price as amount
                $data['amount'] = $existingTicket->getPrice();
            }

            // Validate quantity if provided
            if ($requestedQuantity < 1) {
                return new JsonResponse(['error' => 'Quantity must be at least 1'], 400);
            }

            // Validate input
            $validation = $this->validatePaymentInput($data);
            if (!$validation['valid']) {
                return new JsonResponse(['error' => $validation['error']], 400);
            }

            // Get authenticated user
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'User not authenticated'], 401);
            }


            // Create payment record (PENDING)
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setAmount($data['amount']);
            $payment->setCurrency($data['currency'] ?? 'USD');
            $payment->setStatus('PENDING');
            $entityManager->persist($payment);
            $entityManager->flush();

            // Convert amount to cents for Stripe
            $amountInCents = (int)(((float)$data['amount']) * 100);

            // Process payment via Stripe
            $cardData = [
                'number' => $data['card_number'],
                'exp_month' => $data['expiry_month'],
                'exp_year' => $data['expiry_year'],
                'cvc' => $data['cvv'],
                'name' => $data['first_name'] . ' ' . $data['last_name'],
            ];

            $paymentResult = $stripeService->processCardPayment(
                $cardData,
                $amountInCents,
                strtolower($data['currency'] ?? 'usd'),
                $data['event_name'] ?? 'Event Ticket'
            );

            if (!$paymentResult['success']) {
                $payment->setStatus('FAILED');
                $payment->setErrorMessage($paymentResult['error']);
                $entityManager->flush();

                // Return professional error message
                $errorMessage = $paymentResult['error'];

                // Map common Stripe errors to user-friendly messages
                $stripeErrorMap = [
                    'Your card has been declined' => 'Your card was declined. Please check your card details or use a different card.',
                    'Invalid card number' => 'The card number you entered is invalid.',
                    'Your card has expired' => 'Your card has expired. Please use a valid card.',
                    'Invalid CVC' => 'The security code (CVC) is invalid.',
                    'incorrect_cvc' => 'The security code (CVC) does not match.',
                    'expired_card' => 'Your card has expired.',
                    'card_declined' => 'Your card was declined.',
                    'processing_error' => 'There was an error processing your payment. Please try again.',
                ];

                // Check if error matches known patterns
                foreach ($stripeErrorMap as $key => $userMessage) {
                    if (stripos($errorMessage, $key) !== false) {
                        $errorMessage = $userMessage;
                        break;
                    }
                }

                return new JsonResponse([
                    'success' => false,
                    'error' => $errorMessage,
                    'payment_id' => $payment->getPaymentId(),
                    'type' => 'card_error'
                ], 400);
            }

            // At this point Stripe charged the card successfully. We must finalize DB changes
            // in a transaction and ensure we do not oversell. If finalization fails, refund the charge.
            $conn = $entityManager->getConnection();
            $conn->beginTransaction();

            try {
                if ($existingTicket) {
                    // Lock the ticket row for update and re-check quantity using raw query
                    $ticketId = $existingTicket->getId();
                    $row = $conn->fetchAssociative('SELECT quantity FROM tickets WHERE id = ? FOR UPDATE', [$ticketId]);
                    $available = (int)($row['quantity'] ?? 0);

                    if ($available < $requestedQuantity) {
                        // Not enough inventory — refund and mark payment failed
                        $stripeService->refundCharge($paymentResult['transaction_id']);
                        $payment->setStatus('FAILED');
                        $payment->setErrorMessage('Insufficient inventory at finalization');
                        $entityManager->flush();
                        $conn->commit();

                        return new JsonResponse([
                            'success' => false,
                            'error' => 'Insufficient inventory at finalization',
                            'available' => $available
                        ], 409);
                    }

                    // Update ORM entity and link
                    $existingTicket->decrementQuantity($requestedQuantity);
                    $payment->setTicket($existingTicket);
                    $payment->setStatus('COMPLETED');
                    $payment->setPaypalTransactionId($paymentResult['transaction_id']);
                    $payment->setCardBrand($paymentResult['card_brand']);
                    $payment->setCardLastFour($paymentResult['card_last_four']);
                    $payment->setCompletedAt(new \DateTimeImmutable());

                    $entityManager->flush();
                    $conn->commit();
                    $ticket = $existingTicket;

                } else {
                    // No existing ticket: create ticket and associate
                    $ticket = new Ticket();
                    $ticket->setUser($user);
                    $ticket->setPayment($payment);
                    $ticket->setEventName($data['event_name'] ?? 'Event Ticket');
                    $ticket->setTicketType($data['ticket_type'] ?? 'GENERAL');
                    $ticket->setPrice($data['amount']);
                    $ticket->setStatus('ACTIVE');
                    $ticket->setQuantity($requestedQuantity);
                    $ticket->setExpiresAt(new \DateTimeImmutable('+30 days'));

                    $payment->setTicket($ticket);
                    $payment->setStatus('COMPLETED');
                    $payment->setPaypalTransactionId($paymentResult['transaction_id']);
                    $payment->setCardBrand($paymentResult['card_brand']);
                    $payment->setCardLastFour($paymentResult['card_last_four']);
                    $payment->setCompletedAt(new \DateTimeImmutable());

                    $entityManager->persist($ticket);
                    $entityManager->flush();
                    $conn->commit();
                }
            } catch (\Exception $e) {
                // Rollback and attempt refund
                try { $conn->rollBack(); } catch (\Exception $_) {}
                try { $stripeService->refundCharge($paymentResult['transaction_id']); } catch (\Exception $_) {}
                $payment->setStatus('FAILED');
                $payment->setErrorMessage('Finalization failed: ' . $e->getMessage());
                $entityManager->flush();

                return new JsonResponse(['error' => 'Payment finalized but DB update failed: ' . $e->getMessage()], 500);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment' => [
                    'id' => $payment->getPaymentId(),
                    'status' => $payment->getStatus(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'card_brand' => $payment->getCardBrand(),
                    'card_last_four' => $payment->getCardLastFour(),
                    'transaction_id' => $payment->getPaypalTransactionId(),
                ],
                'ticket' => [
                    'key' => $ticket->getTicketKey(),
                    'event_name' => $ticket->getEventName(),
                    'ticket_type' => $ticket->getTicketType(),
                    'price' => $ticket->getPrice(),
                    'status' => $ticket->getStatus(),
                    'issued_at' => $ticket->getIssuedAt()->format('Y-m-d H:i:s'),
                    'expires_at' => $ticket->getExpiresAt()?->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Payment processing error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/status/{paymentId}', name: 'api_payment_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getPaymentStatus(
        string $paymentId,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $payment = $entityManager->getRepository(Payment::class)->findOneBy([
                'paymentId' => $paymentId
            ]);

            if (!$payment) {
                return new JsonResponse(['error' => 'Payment not found'], 404);
            }

            // Verify user owns this payment
            if ($payment->getUser() !== $this->getUser()) {
                return new JsonResponse(['error' => 'Unauthorized'], 403);
            }

            $ticket = $payment->getTicket();

            return new JsonResponse([
                'payment' => [
                    'id' => $payment->getPaymentId(),
                    'status' => $payment->getStatus(),
                    'amount' => $payment->getAmount(),
                    'currency' => $payment->getCurrency(),
                    'card_brand' => $payment->getCardBrand(),
                    'card_last_four' => $payment->getCardLastFour(),
                    'created_at' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
                    'completed_at' => $payment->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'error_message' => $payment->getErrorMessage(),
                ],
                'ticket' => $ticket ? [
                    'key' => $ticket->getTicketKey(),
                    'event_name' => $ticket->getEventName(),
                    'ticket_type' => $ticket->getTicketType(),
                    'status' => $ticket->getStatus(),
                    'issued_at' => $ticket->getIssuedAt()->format('Y-m-d H:i:s'),
                    'expires_at' => $ticket->getExpiresAt()?->format('Y-m-d H:i:s'),
                ] : null
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error fetching payment status'], 500);
        }
    }

    #[Route('/validate-card', name: 'api_payment_validate_card', methods: ['POST'])]
    public function validateCard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $validation = $this->validatePaymentInput($data, validateOnly: true);

        if (!$validation['valid']) {
            return new JsonResponse([
                'valid' => false,
                'error' => $validation['error']
            ], 400);
        }

        return new JsonResponse([
            'valid' => true,
            'message' => 'Card details are valid'
        ], 200);
    }

    private function validatePaymentInput(array $data, bool $validateOnly = false): array
    {
        $required = [
            'card_number',
            'expiry_month',
            'expiry_year',
            'cvv',
            'first_name',
            'last_name',
            'amount'
        ];

        if (!$validateOnly) {
            $required[] = 'event_name';
        }

        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
                return ['valid' => false, 'error' => "Missing required field: $field"];
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            return ['valid' => false, 'error' => 'Invalid amount'];
        }

        return ['valid' => true];
    }
}

