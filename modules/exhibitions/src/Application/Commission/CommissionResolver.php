<?php

namespace Modules\Exhibition\Application\Commission;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;

final readonly class CommissionResolver
{
    public function __construct(private ExhibitionSettingsService $settings) {}

    public function forArtwork(string|object $artwork): CommissionResolution
    {
        $artwork = is_object($artwork)
            ? $artwork
            : DB::table('exhibition_artworks')->where('public_id', $artwork)->first();
        if (! $artwork) {
            throw new DomainException('Commission cannot be resolved for an unavailable artwork.');
        }

        return $this->forArtistIdentity((int) $artwork->artist_identity_id);
    }

    public function forArtistIdentity(int $artistIdentityId): CommissionResolution
    {
        if ($artistIdentityId < 1 || ! DB::table('exhibition_artist_identities')->where('id', $artistIdentityId)->exists()) {
            throw new DomainException('Commission cannot be resolved for an unavailable artist.');
        }

        $allowArtist = (bool) $this->settings->value('exhibitions.commission.allow_artist_override', true);
        if ($allowArtist) {
            $effectiveTerm = Schema::hasTable('exhibition_artist_commission_terms')
                ? DB::table('exhibition_artist_commission_terms')
                    ->where('artist_identity_id', $artistIdentityId)
                    ->where('effective_from', '<=', now())
                    ->latest('effective_from')
                    ->latest('id')
                    ->first()
                : null;

            if ($effectiveTerm?->source === 'custom' && $effectiveTerm->commission_basis_points !== null) {
                $basisPoints = (int) $effectiveTerm->commission_basis_points;

                return new CommissionResolution($basisPoints, $this->fromBasisPoints($basisPoints), 'artist_custom');
            }
        }

        $rate = $this->settings->value('exhibitions.commission.default_rate');
        if ($rate === null && Schema::hasTable('exhibition_settings')) {
            $legacy = DB::table('exhibition_settings')->where('key', 'commission_basis_points')->value('value');
            if ($legacy !== null) {
                $basisPoints = (int) json_decode((string) $legacy, true);

                return new CommissionResolution($basisPoints, $this->fromBasisPoints($basisPoints), 'legacy_default');
            }
        }
        $rate ??= '20.000';

        return $this->resolution((string) $rate, 'default');
    }

    private function resolution(string $rate, string $source): CommissionResolution
    {
        $basisPoints = $this->toBasisPoints($rate);
        $minimum = $this->toBasisPoints((string) $this->settings->value('exhibitions.commission.minimum_rate', '0.000'));
        $maximum = $this->toBasisPoints((string) $this->settings->value('exhibitions.commission.maximum_rate', '100.000'));
        if ($basisPoints < $minimum || $basisPoints > $maximum) {
            throw new DomainException('The applicable commission is outside the configured range.');
        }

        return new CommissionResolution($basisPoints, $this->fromBasisPoints($basisPoints), $source);
    }

    private function toBasisPoints(string $percentage): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($percentage), $parts) !== 1) {
            throw new DomainException('Commission rate must be an exact decimal percentage.');
        }
        $thousandths = ((int) $parts[1] * 1000) + (int) str_pad($parts[2] ?? '', 3, '0');
        if ($thousandths < 0 || $thousandths > 100000) {
            throw new DomainException('Commission rate must be between 0 and 100 percent.');
        }

        return intdiv($thousandths + 5, 10);
    }

    private function fromBasisPoints(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $fraction = ($basisPoints % 100) * 10;

        return $whole.'.'.str_pad((string) $fraction, 3, '0', STR_PAD_LEFT);
    }
}
