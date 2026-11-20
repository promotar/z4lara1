<?php

namespace Modules\Store\Application\Catalog\Categories;

use InvalidArgumentException;
use Modules\Store\Domain\Catalog\CategoryRepository;
use Modules\Store\Domain\Catalog\LocalizedSlug;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepository $categories,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function handle(CreateCategoryCommand $command): string
    {
        $name = trim($command->name);
        $slug = (string) new LocalizedSlug($command->slug);
        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }

        $this->transactions->run(function () use ($command, $name, $slug, $publicId, $now): void {
            $parent = $command->parentPublicId === null
                ? null
                : $this->categories->findByPublicId($command->parentPublicId, true);

            if ($command->parentPublicId !== null && $parent === null) {
                throw new InvalidArgumentException('Parent category does not exist.');
            }

            $categoryId = $this->categories->create(
                publicId: $publicId,
                parentId: $parent?->id,
                depth: $parent === null ? 0 : $parent->depth + 1,
                position: max(0, $command->position),
                code: $command->code,
                catalogSection: $command->catalogSection,
                actor: $command->actor,
                now: $now,
            );
            $this->categories->addTranslation($categoryId, $command->locale, $name, $slug, $command->description, $now);
        });

        return $publicId;
    }
}
