<?php

namespace Modules\Store\Application\CatalogTransfer;

final class ProductCatalogImportResult
{
    public int $rows = 0;
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;
    public int $failed = 0;
    public int $imagesImported = 0;
    public int $imagesSkipped = 0;
    public int $imagesFailed = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @return array<string, int|list<string>> */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'images_imported' => $this->imagesImported,
            'images_skipped' => $this->imagesSkipped,
            'images_failed' => $this->imagesFailed,
            'errors' => array_slice($this->errors, 0, 100),
        ];
    }
}
