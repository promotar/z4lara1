<?php

namespace Modules\Seo\Services;

final class SchemaTemplateValidator
{
    /**
     * @return array{schema: array<string, mixed>|null, errors: array<int, string>}
     */
    public function validate(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            return ['schema' => null, 'errors' => ['Schema must be a JSON object.']];
        }

        $errors = [];

        if (! isset($decoded['@context']) || ! is_string($decoded['@context'])) {
            $errors[] = 'Schema must include a string @context.';
        }

        $types = $decoded['@type'] ?? null;
        $typeList = is_array($types) ? $types : [$types];
        $typeList = array_values(array_filter($typeList, static fn (mixed $type): bool => is_string($type) && trim($type) !== ''));

        if ($typeList === []) {
            $errors[] = 'Schema must include @type.';
        }

        foreach ($typeList as $type) {
            $errors = array_merge($errors, $this->requiredFields($type, $decoded));
        }

        if (isset($decoded['offers'])) {
            $offers = is_array($decoded['offers']) && array_is_list($decoded['offers'])
                ? $decoded['offers']
                : [$decoded['offers']];

            foreach ($offers as $offer) {
                if (! is_array($offer)) {
                    $errors[] = 'offers entries must be objects.';

                    continue;
                }

                $offerTypes = $offer['@type'] ?? null;
                if (! is_string($offerTypes) || strtolower($offerTypes) !== 'offer') {
                    $errors[] = 'Each offers entry must use @type Offer.';
                }

                $errors = array_merge($errors, $this->requiredFields('Offer', $offer));
            }
        }

        if (isset($decoded['itemListElement'])) {
            if (! is_array($decoded['itemListElement']) || $decoded['itemListElement'] === []) {
                $errors[] = 'BreadcrumbList requires a non-empty itemListElement array.';
            } else {
                foreach ($decoded['itemListElement'] as $item) {
                    if (! is_array($item) || ! isset($item['position'], $item['name'])) {
                        $errors[] = 'Each breadcrumb item needs position and name.';
                        break;
                    }
                }
            }
        }

        if (isset($decoded['mainEntity'])) {
            if (! is_array($decoded['mainEntity']) || $decoded['mainEntity'] === []) {
                $errors[] = 'FAQPage requires a non-empty mainEntity array.';
            } else {
                foreach ($decoded['mainEntity'] as $entity) {
                    if (! is_array($entity) || ! isset($entity['name']) || ! isset($entity['acceptedAnswer'])) {
                        $errors[] = 'Each FAQ item needs name and acceptedAnswer.';
                        break;
                    }
                }
            }
        }

        return [
            'schema' => $errors === [] ? $decoded : null,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function requiredFields(string $type, array $schema): array
    {
        $type = strtolower($type);

        $required = match ($type) {
            'organization', 'website' => ['name', 'url'],
            'webpage' => [['name', 'headline'], 'url'],
            'article', 'blogposting', 'newsarticle' => [['headline', 'name']],
            'course' => ['name', ['description'], ['provider', 'offers']],
            'product' => ['name', 'offers'],
            'visualartwork' => ['name'],
            'person' => ['name'],
            'event' => ['name', 'startDate', 'location'],
            'localbusiness' => ['name', 'address'],
            'offer' => ['price', 'priceCurrency'],
            default => [],
        };

        $errors = [];

        foreach ($required as $field) {
            $alternatives = is_array($field) ? $field : [$field];

            $present = false;
            foreach ($alternatives as $alternative) {
                if (isset($schema[$alternative]) && $schema[$alternative] !== '' && $schema[$alternative] !== []) {
                    $present = true;
                    break;
                }
            }

            if (! $present) {
                $errors[] = $type.' requires '.implode(' or ', $alternatives).'.';
            }
        }

        return $errors;
    }
}
