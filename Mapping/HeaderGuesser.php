<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CsvImporter\Mapping;

final readonly class HeaderGuesser
{
    private const FEATURE_PREFIX = 'c:';
    private const ATTRIBUTE_PREFIX = 'd:';

    private const SYNONYMS = [
        'reference' => ['ref', 'reference', 'ref produit', 'reference produit', 'code', 'code produit', 'sku', 'code article', 'ref article'],
        'title' => ['titre', 'nom', 'nom produit', 'designation', 'libelle', 'name', 'title', 'produit', 'intitule'],
        'chapo' => ['description courte', 'resume', 'chapo', 'short description', 'accroche'],
        'description' => ['description', 'description longue', 'long description', 'descriptif'],
        'price' => ['prix', 'prix ht', 'prix de vente', 'prix vente ht', 'tarif', 'pu ht', 'price', 'prix unitaire'],
        'promo_price' => ['prix promo', 'prix promotionnel', 'prix solde', 'sale price'],
        'stock' => ['stock', 'quantite', 'qte', 'qty', 'quantity', 'stock disponible'],
        'weight' => ['poids', 'weight', 'poids kg'],
        'category' => ['categorie', 'famille', 'rayon', 'category', 'categorie principale'],
        'brand' => ['marque', 'brand', 'fabricant'],
        'template' => ['gabarit', 'template', 'type de produit'],
        'image' => ['image', 'images', 'img', 'photo', 'url image', 'image url', 'visuel', 'adresse image'],
        'ean' => ['ean', 'ean13', 'code ean', 'gtin', 'code barre', 'code barres'],
    ];

    public function guess(array $headers): array
    {
        $lookup = [];

        foreach (self::SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
                $lookup[$synonym] = CatalogField::from($field);
            }
        }

        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $taken = [];
        $proposal = [];

        foreach ($headers as $header) {
            $proposal[$header] = $this->prefixed($header) ?? $this->synonym($header, $lookup, $transliterator, $taken);
        }

        return $proposal;
    }

    private function prefixed(string $header): ?ColumnTarget
    {
        $trimmed = ltrim($header);
        $prefix = strtolower(substr($trimmed, 0, 2));
        $name = trim(substr($trimmed, 2));

        if ('' === $name) {
            return null;
        }

        return match ($prefix) {
            self::FEATURE_PREFIX => new ColumnTarget(CatalogField::Feature, $name),
            self::ATTRIBUTE_PREFIX => new ColumnTarget(CatalogField::Attribute, $name),
            default => null,
        };
    }

    private function synonym(string $header, array $lookup, \Transliterator $transliterator, array &$taken): ?ColumnTarget
    {
        $normalised = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $transliterator->transliterate($header)));
        $field = $lookup[$normalised] ?? null;

        if (null === $field || isset($taken[$field->value])) {
            return null;
        }

        $taken[$field->value] = true;

        return new ColumnTarget($field);
    }
}
