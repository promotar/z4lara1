<?php

namespace Modules\Exhibition\Application\Admin;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Exhibition\Application\Media\ArtworkMediaService;
use Modules\Exhibition\Application\Security\SpreadsheetCellSanitizer;
use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use RuntimeException;
use SplFileObject;
use Throwable;

final readonly class ArtworkCsvService
{
    private const MAX_ROWS = 5000;

    private const FIELDS = [
        'public_id', 'title', 'artist_email', 'state', 'completion_year', 'work_type', 'collection_name',
        'edition_type', 'edition_number', 'edition_total', 'category', 'artistic_style', 'subject', 'technique',
        'material', 'surface', 'primary_colors', 'orientation', 'width', 'height', 'depth', 'dimension_unit',
        'weight', 'weight_unit', 'is_framed', 'frame_material', 'condition', 'ready_to_hang',
        'short_description', 'detailed_description', 'artwork_story', 'inspiration_source', 'care_instructions',
        'keywords', 'image_alt_text', 'seo_title', 'meta_description', 'requested_price', 'currency',
        'frame_cost', 'packaging_cost', 'availability', 'preparation_days', 'shipping_notes', 'image_urls',
    ];

    public function __construct(
        private AdminArtworkService $artworks,
        private ArtworkMediaService $media,
        private CoreUserGateway $users,
    ) {}

    /** @return iterable<list<string>> */
    public function exportRows(): iterable
    {
        yield self::FIELDS;

        $lastId = 0;
        do {
            $records = DB::table('exhibition_artworks as artwork')
                ->join('exhibition_artist_identities as identity', 'identity.id', '=', 'artwork.artist_identity_id')
                ->where('artwork.id', '>', $lastId)
                ->select('artwork.*', 'identity.core_user_id')
                ->orderBy('artwork.id')
                ->limit(250)
                ->get();
            if ($records->isEmpty()) {
                break;
            }

            $lastId = (int) $records->last()->id;
            $artworkIds = $records->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $images = DB::table('exhibition_artwork_media')
                ->whereIn('artwork_id', $artworkIds)
                ->where('state', 'ready')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->get(['artwork_id', 'public_id'])
                ->groupBy('artwork_id');
            $users = $this->users->findMany($records->pluck('core_user_id')->map(static fn ($id): int => (int) $id)->all());

            foreach ($records as $artwork) {
                $values = (array) $artwork;
                $values['artist_email'] = (string) ($users[(int) $artwork->core_user_id]->email ?? '');
                $values['primary_colors'] = $this->csvList($values['primary_colors'] ?? null);
                $values['keywords'] = $this->csvList($values['keywords'] ?? null);
                $values['image_urls'] = $artwork->state === 'published'
                    ? $images->get($artwork->id, collect())->map(function (object $media) use ($artwork): string {
                        $permalink = filled($artwork->slug ?? null) ? (string) $artwork->slug : (string) $artwork->public_id;

                        return route('exhibitions.artworks.media', [$permalink, $media->public_id]);
                    })->implode('|')
                    : '';

                yield SpreadsheetCellSanitizer::row(array_map(
                    static fn (string $field): mixed => $values[$field] ?? '',
                    self::FIELDS,
                ));
            }
        } while (true);
    }

    /**
     * @return array{read:int,created:int,updated:int,failed:int,images:int,errors:list<string>}
     */
    public function import(UploadedFile $file, int $actorUserId, ?int $fallbackArtistUserId = null): array
    {
        $csv = new SplFileObject($file->getRealPath(), 'rb');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $headers = $csv->fgetcsv();
        if (! is_array($headers)) {
            throw new DomainException('The CSV file is empty.');
        }
        $headers = array_map(function ($header): string {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);

            return Str::snake(trim($header));
        }, $headers);
        if (! in_array('title', $headers, true)) {
            throw new DomainException('The CSV must include a title column.');
        }

        $result = ['read' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'images' => 0, 'errors' => []];
        $line = 1;
        while (! $csv->eof()) {
            $columns = $csv->fgetcsv();
            $line++;
            if (! is_array($columns) || $columns === [null] || $this->blankRow($columns)) {
                continue;
            }
            $result['read']++;
            if ($result['read'] > self::MAX_ROWS) {
                throw new DomainException('One import is limited to '.self::MAX_ROWS.' artwork rows.');
            }

            try {
                $columns = array_pad($columns, count($headers), null);
                $row = array_combine($headers, array_slice($columns, 0, count($headers)));
                if (! is_array($row)) {
                    throw new DomainException('The row columns do not match the header.');
                }
                $row = $this->cleanRow($row);
                if ($row['title'] === '') {
                    throw new DomainException('Artwork title is required.');
                }

                $owner = $fallbackArtistUserId ?: $actorUserId;
                if ($row['artist_email'] !== '') {
                    $matched = $this->users->findByEmail($row['artist_email']);
                    if ($matched === null) {
                        throw new DomainException('No platform user matches artist_email '.$row['artist_email'].'.');
                    }
                    $owner = (int) $matched->id;
                }
                $publicId = $this->publicId($row, $owner);
                $existing = DB::table('exhibition_artworks')->where('public_id', $publicId)->first();
                $payload = $this->payload($row) + ['artist_user_id' => $owner];
                $artwork = $existing
                    ? $this->artworks->save($actorUserId, $payload, $publicId)
                    : $this->artworks->save($actorUserId, $payload, null, $publicId);
                $result[$existing ? 'updated' : 'created']++;

                if (! DB::table('exhibition_artwork_media')->where('artwork_id', $artwork->id)->exists()) {
                    foreach ($this->imageUrls($row['image_urls']) as $url) {
                        $upload = $this->downloadImage($url);
                        try {
                            $this->media->uploadForAdministrator($actorUserId, (string) $artwork->public_id, $upload);
                            $result['images']++;
                        } finally {
                            @unlink($upload->getRealPath());
                        }
                    }
                }
            } catch (Throwable $exception) {
                $result['failed']++;
                if (count($result['errors']) < 25) {
                    $result['errors'][] = 'Row '.$line.': '.$exception->getMessage();
                }
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function payload(array $row): array
    {
        $fields = array_diff(self::FIELDS, ['public_id', 'artist_email', 'state', 'image_urls']);
        $payload = array_intersect_key($row, array_flip($fields));
        foreach (['completion_year', 'edition_number', 'edition_total', 'preparation_days'] as $field) {
            $payload[$field] = $payload[$field] === '' ? null : (int) $payload[$field];
        }
        foreach (['width', 'height', 'depth', 'weight', 'requested_price', 'frame_cost', 'packaging_cost'] as $field) {
            $payload[$field] = $payload[$field] === '' ? null : $payload[$field];
        }
        foreach (['is_framed', 'ready_to_hang'] as $field) {
            $payload[$field] = in_array(mb_strtolower((string) $payload[$field]), ['1', 'true', 'yes', 'on'], true);
        }
        $payload['edition_type'] = $payload['edition_type'] ?: 'original';
        $payload['dimension_unit'] = $payload['dimension_unit'] ?: 'cm';
        $payload['weight_unit'] = $payload['weight_unit'] ?: 'kg';
        $payload['currency'] = $payload['currency'] ?: 'JOD';

        return $payload;
    }

    /** @param array<string, mixed> $row @return array<string, string> */
    private function cleanRow(array $row): array
    {
        $clean = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
            if (str_starts_with($value, "'") && preg_match('/^\'[=+\-@]/', $value) === 1) {
                $value = substr($value, 1);
            }
            $clean[$field] = $value;
        }

        return $clean;
    }

    /** @param array<string, string> $row */
    private function publicId(array $row, int $ownerUserId): string
    {
        if ($row['public_id'] !== '') {
            if (! Str::isUuid($row['public_id'])) {
                throw new DomainException('public_id must be a valid UUID when supplied.');
            }

            return $row['public_id'];
        }

        $hash = md5(implode('|', [
            'exhibitions-artwork-import-v1',
            $ownerUserId,
            mb_strtolower($row['title']),
            $row['completion_year'],
            mb_strtolower($row['category']),
        ]));

        return sprintf('%s-%s-5%s-a%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), substr($hash, 17, 3), substr($hash, 20, 12));
    }

    /** @return list<string> */
    private function imageUrls(string $value): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('trim', preg_split('/[|\r\n]+/', $value) ?: [])))), 0, 8);
    }

    private function downloadImage(string $url): UploadedFile
    {
        $current = $url;
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            [$host, $port, $ip] = $this->safeRemoteTarget($current);
            $response = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $port, $ip)]],
            ])->connectTimeout(5)->timeout(25)->get($current);
            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! is_string($location) || ! preg_match('#^https?://#i', $location)) {
                    throw new DomainException('Image redirects must use an absolute HTTP or HTTPS URL.');
                }
                $current = $location;

                continue;
            }
            if (! $response->successful()) {
                throw new DomainException('An artwork image URL returned HTTP '.$response->status().'.');
            }
            $bytes = $response->body();
            if ($bytes === '' || strlen($bytes) > 20 * 1024 * 1024) {
                throw new DomainException('An artwork image is empty or exceeds 20 MB.');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => throw new DomainException('Remote artwork images must be JPEG, PNG, or WebP.'),
            };
            $path = tempnam(sys_get_temp_dir(), 'z4-exhibition-import-');
            if ($path === false || file_put_contents($path, $bytes) === false) {
                throw new RuntimeException('The imported image could not be staged safely.');
            }

            return new UploadedFile($path, 'artwork.'.$extension, $mime, null, true);
        }

        throw new DomainException('The artwork image URL redirected too many times.');
    }

    /** @return array{string,int,string} */
    private function safeRemoteTarget(string $url): array
    {
        $parts = parse_url($url);
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('Artwork image URLs must be public HTTP or HTTPS addresses without credentials.');
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new DomainException('Artwork image URLs may use only standard HTTP or HTTPS ports.');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (! is_array($ips) || $ips === []) {
            throw new DomainException('The artwork image host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new DomainException('Private or reserved network addresses are not allowed for artwork imports.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** @param list<mixed> $row */
    private function blankRow(array $row): bool
    {
        return count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0;
    }

    private function csvList(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? implode(', ', $decoded) : $value;
    }
}
