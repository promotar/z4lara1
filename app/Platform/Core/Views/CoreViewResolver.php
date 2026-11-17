<?php

namespace App\Platform\Core\Views;

class CoreViewResolver
{
    public function __construct(
        private readonly ViewPathGuard $guard,
    ) {
        //
    }

    public function resolve(string $view): ?string
    {
        $relativePath = $this->guard->viewToRelativePath($view);

        foreach ($this->coreViewRoots() as $root) {
            $path = $this->guard->pathInside($root, $relativePath);

            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function existingRoots(): array
    {
        return array_values(array_filter($this->coreViewRoots(), fn (string $root): bool => is_dir($root)));
    }

    /**
     * @return array<int, string>
     */
    private function coreViewRoots(): array
    {
        return [
            base_path('app/Platform/Core/resources/views'),
            resource_path('views'),
        ];
    }
}
