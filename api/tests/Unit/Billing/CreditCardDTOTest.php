<?php

namespace Tests\Unit\Billing;

use App\Modules\Billing\DTOs\CreditCardDTO;
use App\Modules\Billing\Support\PaymentDataRules;
use PHPUnit\Framework\TestCase;

class CreditCardDTOTest extends TestCase
{
    public function test_try_from_nested_credit_card(): void
    {
        $card = CreditCardDTO::tryFromPaymentData([
            'credit_card' => [
                'holder_name' => 'João Silva',
                'number' => '4111 1111 1111 1111',
                'expiration_month' => '5',
                'expiration_year' => '30',
                'cvv' => '123',
                'postal_code' => '01310-100',
                'address_number' => '42',
            ],
        ]);

        $this->assertNotNull($card);
        $this->assertSame('João Silva', $card->holderName);
        $this->assertSame('4111111111111111', $card->number);
        $this->assertSame('05', $card->expirationMonth);
        $this->assertSame('2030', $card->expirationYear);
        $this->assertSame('123', $card->cvv);
        $this->assertSame('01310100', $card->postalCode);
        $this->assertSame('42', $card->addressNumber);
    }

    public function test_try_from_flat_card_aliases(): void
    {
        $card = CreditCardDTO::tryFromPaymentData([
            'cardHolderName' => 'Ana Costa',
            'cardNumber' => '5500000000000004',
            'cardExpirationMonth' => '12',
            'cardExpirationYear' => '2028',
            'cardCvv' => '456',
        ]);

        $this->assertNotNull($card);
        $this->assertSame('Ana Costa', $card->holderName);
        $this->assertSame('5500000000000004', $card->number);
        $this->assertSame('12', $card->expirationMonth);
        $this->assertSame('2028', $card->expirationYear);
        $this->assertSame('456', $card->cvv);
    }

    public function test_returns_null_when_incomplete(): void
    {
        $this->assertNull(CreditCardDTO::tryFromPaymentData([
            'cardHolderName' => 'Ana Costa',
            'cardNumber' => '5500000000000004',
        ]));
    }

    public function test_sanitize_keeps_credit_card_and_token(): void
    {
        $sanitized = PaymentDataRules::sanitize([
            'credit_card_token' => 'tok_abc',
            'credit_card' => [
                'holder_name' => 'João',
                'number' => '4111111111111111',
                'expiration_month' => '12',
                'expiration_year' => '2030',
                'cvv' => '123',
                'unknown' => 'drop-me',
            ],
            'evil' => 'nope',
            'installments' => 3,
            'recurring' => true,
        ]);

        $this->assertSame('tok_abc', $sanitized['credit_card_token']);
        $this->assertSame(3, $sanitized['installments']);
        $this->assertTrue($sanitized['recurring']);
        $this->assertTrue(PaymentDataRules::resolveRecurring($sanitized));
        $this->assertArrayNotHasKey('evil', $sanitized);
        $this->assertSame('João', $sanitized['credit_card']['holder_name']);
        $this->assertArrayNotHasKey('unknown', $sanitized['credit_card']);
    }
}
