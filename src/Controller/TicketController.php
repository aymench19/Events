<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tickets')]
#[IsGranted('ROLE_USER')]
class TicketController extends AbstractController
{
    /**
     * Get all tickets for current user
     */
    #[Route('', name: 'api_tickets_list', methods: ['GET'])]
    public function listUserTickets(TicketRepository $ticketRepository): JsonResponse
    {
        $user = $this->getUser();

        $tickets = $ticketRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $data = array_map(function(Ticket $ticket) {
            return $this->formatTicketResponse($ticket);
        }, $tickets);

        return new JsonResponse([
            'success' => true,
            'count' => count($data),
            'tickets' => $data
        ]);
    }

    /**
     * Get single ticket by ID
     */
    #[Route('/{id}', name: 'api_ticket_show', methods: ['GET'])]
    public function showTicket(Ticket $ticket, TicketRepository $ticketRepository): JsonResponse
    {
        // Verify ownership
        if ($ticket->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        return new JsonResponse([
            'success' => true,
            'ticket' => $this->formatTicketResponse($ticket)
        ]);
    }

    /**
     * Create new ticket (admin/event manager only)
     */
    #[Route('', name: 'api_ticket_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createTicket(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            $required = ['event_name', 'ticket_type', 'price', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    return new JsonResponse(['error' => "Missing required field: $field"], 400);
                }
            }

            // Validate price and quantity
            if (!is_numeric($data['price']) || $data['price'] < 0) {
                return new JsonResponse(['error' => 'Price must be a positive number'], 400);
            }

            if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
                return new JsonResponse(['error' => 'Quantity must be a positive number'], 400);
            }

            $ticket = new Ticket();
            $ticket->setUser($this->getUser());
            $ticket->setEventName($data['event_name']);
            $ticket->setTicketType($data['ticket_type']);
            $ticket->setPrice((string)$data['price']);
            $ticket->setQuantity((int)$data['quantity']);
            $ticket->setStatus('ACTIVE');

            if (isset($data['expires_at'])) {
                try {
                    $expiresAt = new \DateTimeImmutable($data['expires_at']);
                    $ticket->setExpiresAt($expiresAt);
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Invalid expiry date format'], 400);
                }
            }

            $entityManager->persist($ticket);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Ticket created successfully',
                'ticket' => $this->formatTicketResponse($ticket)
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error creating ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update ticket quantity or details
     */
    #[Route('/{id}', name: 'api_ticket_update', methods: ['PUT', 'PATCH'])]
    public function updateTicket(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Verify ownership
        if ($ticket->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['quantity'])) {
                if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
                    return new JsonResponse(['error' => 'Quantity must be a non-negative number'], 400);
                }
                $ticket->setQuantity((int)$data['quantity']);
            }

            if (isset($data['status'])) {
                $validStatuses = ['ACTIVE', 'USED', 'EXPIRED', 'CANCELLED'];
                if (!in_array($data['status'], $validStatuses)) {
                    return new JsonResponse(['error' => 'Invalid status'], 400);
                }
                $ticket->setStatus($data['status']);
            }

            if (isset($data['ticket_type'])) {
                $ticket->setTicketType($data['ticket_type']);
            }

            if (isset($data['event_name'])) {
                $ticket->setEventName($data['event_name']);
            }

            if (isset($data['price'])) {
                if (!is_numeric($data['price']) || $data['price'] < 0) {
                    return new JsonResponse(['error' => 'Price must be a positive number'], 400);
                }
                $ticket->setPrice((string)$data['price']);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'ticket' => $this->formatTicketResponse($ticket)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error updating ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Decrement ticket quantity (when purchasing)
     */
    #[Route('/{id}/purchase', name: 'api_ticket_purchase', methods: ['POST'])]
    public function purchaseTicket(
        Ticket $ticket,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $quantity = $data['quantity'] ?? 1;

            if (!is_numeric($quantity) || $quantity < 1) {
                return new JsonResponse(['error' => 'Quantity must be at least 1'], 400);
            }

            if ($ticket->isSoldOut()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Ticket sold out',
                    'ticket' => $this->formatTicketResponse($ticket)
                ], 409);
            }

            if ($ticket->getQuantity() < $quantity) {
                return new JsonResponse([
                    'success' => false,
                    'error' => "Insufficient quantity. Available: {$ticket->getQuantity()}",
                    'available' => $ticket->getQuantity(),
                    'ticket' => $this->formatTicketResponse($ticket)
                ], 400);
            }

            $ticket->decrementQuantity($quantity);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "Successfully purchased $quantity ticket(s)",
                'remaining_quantity' => $ticket->getQuantity(),
                'ticket' => $this->formatTicketResponse($ticket)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error purchasing ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete ticket (mark as cancelled or remove)
     */
    #[Route('/{id}', name: 'api_ticket_delete', methods: ['DELETE'])]
    public function deleteTicket(
        Ticket $ticket,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Verify ownership
        if ($ticket->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        try {
            $entityManager->remove($ticket);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error deleting ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get ticket statistics
     */
    #[Route('/stats/overview', name: 'api_ticket_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getTicketStats(TicketRepository $ticketRepository): JsonResponse
    {
        $allTickets = $ticketRepository->findAll();

        $stats = [
            'total_tickets' => count($allTickets),
            'active_tickets' => 0,
            'sold_out_tickets' => 0,
            'used_tickets' => 0,
            'expired_tickets' => 0,
            'cancelled_tickets' => 0,
            'total_inventory' => 0,
            'available_inventory' => 0,
        ];

        foreach ($allTickets as $ticket) {
            $stats['total_inventory'] += $ticket->getQuantity();
            $stats['available_inventory'] += $ticket->isSoldOut() ? 0 : $ticket->getQuantity();

            match($ticket->getStatus()) {
                'ACTIVE' => $stats['active_tickets']++,
                'USED' => $stats['used_tickets']++,
                'EXPIRED' => $stats['expired_tickets']++,
                'CANCELLED' => $stats['cancelled_tickets']++,
                default => null,
            };

            if ($ticket->isSoldOut()) {
                $stats['sold_out_tickets']++;
            }
        }

        return new JsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Download ticket as PDF with QR code
     */
    #[Route('/{id}/download', name: 'api_ticket_download', methods: ['GET'])]
    public function downloadTicket(
        Ticket $ticket,
        \App\Service\QrCodeService $qrCodeService
    ): Response {
        // Verify ownership
        if ($ticket->getUser() !== $this->getUser()) {
            return new Response('Unauthorized', 403);
        }

        try {
            $user = $this->getUser();
            $userName = $user ? ($user->getFirstName() . ' ' . $user->getLastName()) : 'User';

            // Generate HTML ticket
            $html = $qrCodeService->generateTicketHtml(
                $ticket->getTicketKey(),
                $ticket->getEventName(),
                $ticket->getTicketType(),
                $ticket->getPrice(),
                $userName,
                $ticket->getIssuedAt()->format('Y-m-d H:i:s'),
                $ticket->getExpiresAt()?->format('Y-m-d H:i:s')
            );

            // Create response with HTML
            $response = new Response($html);
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            $response->headers->set('Content-Disposition', 'inline; filename="ticket_' . $ticket->getTicketKey() . '.html"');
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error generating ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get ticket QR code
     */
    #[Route('/{id}/qrcode', name: 'api_ticket_qrcode', methods: ['GET'])]
    public function getTicketQrCode(
        Ticket $ticket,
        \App\Service\QrCodeService $qrCodeService
    ): Response {
        // Verify ownership
        if ($ticket->getUser() !== $this->getUser()) {
            return new Response('Unauthorized', 403);
        }

        try {
            // Generate QR code
            $qrCodeBase64 = $qrCodeService->generateTicketQrCode(
                $ticket->getTicketKey(),
                $ticket->getEventName()
            );

            // Decode to get binary PNG data
            $pngData = base64_decode($qrCodeBase64);

            // Create response
            $response = new Response($pngData);
            $response->headers->set('Content-Type', 'image/png');
            $response->headers->set('Content-Disposition', 'inline; filename="qrcode_' . $ticket->getTicketKey() . '.png"');
            $response->headers->set('Content-Length', strlen($pngData));

            return $response;

        } catch (\Exception $e) {
            return new Response('Error generating QR code: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format ticket response
     */
    private function formatTicketResponse(Ticket $ticket): array
    {
        return [
            'id' => $ticket->getId(),
            'ticket_key' => $ticket->getTicketKey(),
            'event_name' => $ticket->getEventName(),
            'ticket_type' => $ticket->getTicketType(),
            'price' => $ticket->getPrice(),
            'quantity' => $ticket->getQuantity(),
            'available' => !$ticket->isSoldOut(),
            'sold_out' => $ticket->isSoldOut(),
            'status' => $ticket->getStatus(),
            'issued_at' => $ticket->getIssuedAt()->format('Y-m-d H:i:s'),
            'expires_at' => $ticket->getExpiresAt()?->format('Y-m-d H:i:s'),
            'qr_code' => $ticket->getQrCode(),
            'download_url' => '/api/tickets/' . $ticket->getId() . '/download',
            'qrcode_url' => '/api/tickets/' . $ticket->getId() . '/qrcode',
        ];
    }
}
