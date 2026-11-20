<?php

use App\Platform\Core\Hooks\HookManager;

return static function (HookManager $hooks): void {
    // Login integration lives in the active service provider because the core
    // auth.login Blade view currently has no dedicated markup hook.
};
