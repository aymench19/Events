<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.stripe.com/v1';

    public function __construct(
        private HttpClientInterface $httpClient,
        string $stripeApiKey
    ) {
        $this->apiKey = $stripeApiKey;
    }

    /**
     * Validate and process card payment with Stripe
     *
     * @param array $cardData Format: ['number' => '', 'exp_month' => '', 'exp_year' => '', 'cvc' => '', 'name' => '']
     * @param float $amount Amount in cents (e.g., 9999 = $99.99)
     * @param string $currency Currency code
     * @param string $description Payment description
     * @return array
     */
    public function processCardPayment(array $cardData, float $amount, string $currency = 'usd', string $description = 'Event Ticket'): array
    {
        try {
            // Validate card data
            $validation = $this->validateCardData($cardData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error'],
                    'statusCode' => 400,
                    'type' => 'validation_error'
                ];
            }

            // Create a payment method token
            $tokenResult = $this->createCardToken($cardData);
            if (!$tokenResult['success']) {
                // Stripe token creation failed - card is invalid or doesn't exist
                $error = $tokenResult['error'] ?? 'Card validation failed';

                // Map Stripe errors to user-friendly messages
                if (stripos($error, 'card_declined') !== false || stripos($error, 'decline') !== false) {
                    $error = 'Your card was declined. Please check your card details or try a different card.';
                } elseif (stripos($error, 'incorrect_cvc') !== false || stripos($error, 'cvc') !== false) {
                    $error = 'The security code (CVC) you provided is incorrect.';
                } elseif (stripos($error, 'expired_card') !== false || stripos($error, 'expired') !== false) {
                    $error = 'Your card has expired. Please use a valid card.';
                } elseif (stripos($error, 'invalid_card') !== false || stripos($error, 'invalid') !== false) {
                    $error = 'The card number you entered is invalid. Please check and try again.';
                } elseif (stripos($error, 'lost_card') !== false) {
                    $error = 'This card has been reported as lost. Please use a different card.';
                } elseif (stripos($error, 'stolen_card') !== false) {
                    $error = 'This card has been reported as stolen. Please use a different card.';
                }

                return [
                    'success' => false,
                    'error' => $error,
                    'statusCode' => 400,
                    'type' => 'card_error'
                ];
            }

            $tokenId = $tokenResult['token_id'];

            // Create a payment intent
            $paymentIntentResult = $this->createPaymentIntent(
                (int)$amount,
                $currency,
                $tokenId,
                $description
            );

            if (!$paymentIntentResult['success']) {
                $error = $paymentIntentResult['error'] ?? 'Payment failed';

                // Map common Stripe charge errors
                if (stripos($error, 'card_declined') !== false) {
                    $error = 'Your card was declined by your bank. Please try a different card.';
                } elseif (stripos($error, 'insufficient_funds') !== false) {
                    $error = 'Your card does not have sufficient funds for this transaction.';
                } elseif (stripos($error, 'expired_card') !== false) {
                    $error = 'Your card has expired.';
                } elseif (stripos($error, 'incorrect_cvc') !== false) {
                    $error = 'The security code (CVC) is incorrect.';
                } elseif (stripos($error, 'lost_card') !== false) {
                    $error = 'This card has been reported as lost.';
                } elseif (stripos($error, 'stolen_card') !== false) {
                    $error = 'This card has been reported as stolen.';
                } elseif (stripos($error, 'generic_decline') !== false) {
                    $error = 'Your card was declined. Please contact your bank or try a different card.';
                } elseif (stripos($error, 'rate_limit') !== false) {
                    $error = 'Too many requests. Please wait a moment and try again.';
                }

                return [
                    'success' => false,
                    'error' => $error,
                    'statusCode' => $paymentIntentResult['statusCode'] ?? 400,
                    'type' => 'card_error'
                ];
            }

            return [
                'success' => true,
                'transaction_id' => $paymentIntentResult['charge_id'],
                'status' => 'COMPLETED',
                'amount' => $amount / 100, // Convert back to dollars
                'currency' => strtoupper($currency),
                'card_brand' => $this->getCardBrand($cardData['number']),
                'card_last_four' => substr($cardData['number'], -4),
                'statusCode' => 200,
                'type' => 'success'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again later.',
                'statusCode' => 500,
                'type' => 'server_error'
            ];
        }
    }

    /**
     * Create a card token
     */
    private function createCardToken(array $cardData): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/tokens', [
                'auth_bearer' => $this->apiKey,
                'body' => [
                    'card' => [
                        'number' => $cardData['number'],
                        'exp_month' => $cardData['exp_month'],
                        'exp_year' => $cardData['exp_year'],
                        'cvc' => $cardData['cvc'],
                    ],
                ],
            ]);

            $data = $response->toArray();

            if ($response->getStatusCode() === 200 && isset($data['id'])) {
                return [
                    'success' => true,
                    'token_id' => $data['id']
                ];
            }

            $error = $data['error']['message'] ?? 'Card validation failed';
            $errorCode = $data['error']['code'] ?? 'unknown';

            return [
                'success' => false,
                'error' => $error,
                'error_code' => $errorCode,
                'statusCode' => 400
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Card validation failed. Please check your card details.',
                'statusCode' => 500
            ];
        }
    }

    /**
     * Create a payment charge
     */
    private function createPaymentIntent(int $amount, string $currency, string $source, string $description): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/charges', [
                'auth_bearer' => $this->apiKey,
                'body' => [
                    'amount' => $amount,
                    'currency' => $currency,
                    'source' => $source,
                    'description' => $description,
                ],
            ]);

            $data = $response->toArray();

            if ($response->getStatusCode() === 200 && isset($data['id']) && $data['paid'] === true) {
                return [
                    'success' => true,
                    'charge_id' => $data['id'],
                    'statusCode' => 200
                ];
            }

            $error = $data['error']['message'] ?? 'Payment failed';
            $errorCode = $data['error']['code'] ?? 'unknown';

            return [
                'success' => false,
                'error' => $error,
                'error_code' => $errorCode,
                'statusCode' => 400
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Payment processing failed. Please try again later.',
                'statusCode' => 500
            ];
        }
    }

    /**
     * Refund a charge by charge id
     */
    public function refundCharge(string $chargeId): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/refunds', [
                'auth_bearer' => $this->apiKey,
                'body' => [
                    'charge' => $chargeId,
                ],
            ]);

            $data = $response->toArray();

            if (in_array($response->getStatusCode(), [200, 201]) && isset($data['id'])) {
                return [
                    'success' => true,
                    'refund_id' => $data['id']
                ];
            }

            $error = $data['error']['message'] ?? 'Refund failed';
            return [
                'success' => false,
                'error' => $error,
                'statusCode' => $response->getStatusCode()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Refund failed: ' . $e->getMessage(),
                'statusCode' => 500
            ];
        }
    }

    /**
     * Validate card data format with professional error messages
     */
    private function validateCardData(array $cardData): array
    {
        $required = ['number', 'exp_month', 'exp_year', 'cvc', 'name'];

        foreach ($required as $field) {
            if (!isset($cardData[$field]) || empty($cardData[$field])) {
                $fieldNames = [
                    'number' => 'card number',
                    'exp_month' => 'expiry month',
                    'exp_year' => 'expiry year',
                    'cvc' => 'security code (CVC)',
                    'name' => 'cardholder name'
                ];
                return ['valid' => false, 'error' => "Please enter your " . ($fieldNames[$field] ?? $field)];
            }
        }

        // Validate card number (Luhn algorithm)
        if (!$this->validateCardNumber($cardData['number'])) {
            return ['valid' => false, 'error' => 'The card number you entered is invalid. Please check and try again.'];
        }

        // Validate expiry
        $expiryValidation = $this->validateExpiry($cardData['exp_month'], $cardData['exp_year']);
        if (isset($expiryValidation['error'])) {
            return ['valid' => false, 'error' => $expiryValidation['error']];
        }

        // Validate CVC
        if (!$this->validateCVC($cardData['cvc'])) {
            return ['valid' => false, 'error' => 'The security code (CVC) must be 3 or 4 digits.'];
        }

        return ['valid' => true];
    }

    /**
     * Luhn algorithm for card validation
     */
    private function validateCardNumber(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number);

        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }

        $sum = 0;
        $isEven = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int)$number[$i];

            if ($isEven) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $isEven = !$isEven;
        }

        return $sum % 10 === 0;
    }

    /**
     * Validate card expiry date with professional error messages
     */
    private function validateExpiry(string $month, string $year): array
    {
        $month = (int)$month;
        $year = (int)$year;

        if ($month < 1 || $month > 12) {
            return ['valid' => false, 'error' => 'The expiry month must be between 01 and 12.'];
        }

        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        // Accept 2-digit or 4-digit year
        if ($year < 100) {
            $year += 2000;
        }

        if ($year < $currentYear) {
            return ['valid' => false, 'error' => 'Your card has expired. Please use a valid card.'];
        }

        if ($year === $currentYear && $month < $currentMonth) {
            return ['valid' => false, 'error' => 'Your card has expired. Please use a valid card.'];
        }

        return ['valid' => true];
    }

    /**
     * Validate CVC
     */
    private function validateCVC(string $cvc): bool
    {
        return preg_match('/^\d{3,4}$/', $cvc) === 1;
    }

    /**
     * Detect card brand
     */
    private function getCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwoDigits = (int)substr($cardNumber, 0, 2);
        $firstFourDigits = (int)substr($cardNumber, 0, 4);

        if ($firstDigit === '4') {
            return 'VISA';
        } elseif ($firstTwoDigits >= 51 && $firstTwoDigits <= 55) {
            return 'MASTERCARD';
        } elseif ($firstTwoDigits === 37 || $firstTwoDigits === 34) {
            return 'AMEX';
        } elseif ($firstFourDigits === 6011 || ($firstTwoDigits >= 65 && $firstTwoDigits <= 69)) {
            return 'DISCOVER';
        } elseif ($firstFourDigits >= 3528 && $firstFourDigits <= 3589) {
            return 'JCB';
        }

        return 'UNKNOWN';
    }
}
