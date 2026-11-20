<?php

namespace Modules\Exhibition\Domain\Artists;

enum ArtistAccountState: string
{
    case Active = 'active';
    case Restricted = 'restricted';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return __('exhibitions::ui.account_state_'.$this->value);
    }

    /** @return array<string, bool> */
    public function capabilities(): array
    {
        return match ($this) {
            self::Active => ['dashboard' => true, 'create_artwork' => true, 'existing_artwork' => true, 'fulfillment' => true, 'settlements' => true],
            self::Restricted => ['dashboard' => true, 'create_artwork' => false, 'existing_artwork' => true, 'fulfillment' => true, 'settlements' => true],
            self::Suspended => ['dashboard' => false, 'create_artwork' => false, 'existing_artwork' => true, 'fulfillment' => true, 'settlements' => true],
            self::Terminated => ['dashboard' => false, 'create_artwork' => false, 'existing_artwork' => false, 'fulfillment' => true, 'settlements' => true],
        };
    }
}
