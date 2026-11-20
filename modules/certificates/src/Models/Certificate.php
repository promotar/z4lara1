<?php

namespace Modules\Certificates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Certificates\Support\CertificateNameNormalizer;

class Certificate extends Model
{
    use SoftDeletes;

    protected $table = 'certificates';

    public $timestamps = false;

    protected $fillable = ['name', 'date', 'slug', 'image'];

    protected function casts(): array
    {
        return ['date' => 'datetime'];
    }

    public function setNameAttribute(?string $value): void
    {
        $value = (string) $value;
        $this->attributes['name'] = $value;
        $this->attributes['name_normalized'] = CertificateNameNormalizer::normalize($value);
    }

    public static function findExact(string $slug): ?self
    {
        $certificate = static::query()->where('slug', $slug)->first();

        return $certificate && $certificate->slug === $slug ? $certificate : null;
    }

    public function publicUrl(): string
    {
        return url('/'.rawurlencode($this->slug).'/');
    }
}
