<?php

namespace Tests\Unit;

use Modules\Seo\Services\SchemaTemplateValidator;
use Tests\TestCase;

class SchemaTemplateValidatorTest extends TestCase
{
    public function test_valid_organization_schema_passes(): void
    {
        $result = (new SchemaTemplateValidator())->validate(json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'ART INPA',
            'url' => 'https://artinpa.test',
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertIsArray($result['schema']);
    }

    public function test_product_with_offer_passes_and_missing_offer_is_rejected(): void
    {
        $validator = new SchemaTemplateValidator();
        $valid = $validator->validate(json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Artwork',
            'offers' => [
                '@type' => 'Offer',
                'price' => '100.00',
                'priceCurrency' => 'JOD',
            ],
        ]));
        $this->assertSame([], $valid['errors']);

        $invalid = $validator->validate(json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Artwork',
        ]));
        $this->assertNotEmpty($invalid['errors']);
        $this->assertNull($invalid['schema']);
    }

    public function test_invalid_json_and_missing_type_are_rejected(): void
    {
        $validator = new SchemaTemplateValidator();
        $this->assertNotEmpty($validator->validate('{broken')['errors']);
        $this->assertNotEmpty($validator->validate(json_encode([
            '@context' => 'https://schema.org',
            'name' => 'No type',
        ]))['errors']);
    }
}
