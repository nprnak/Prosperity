<?php

namespace Modules\ApplicantManagement\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Modules\ApplicantManagement\Models\Profile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves KYC documents off the private disk.
 *
 * Two routes reach these files — the applicant viewing their own uploads and a
 * reviewer examining someone else's — so the lookup and streaming live here
 * and each caller supplies only the profile it has already authorised.
 */
class ProfileDocumentService
{
    /**
     * URL slug => stored document_type. The slug is what appears in routes,
     * so it stays hyphenated while the column keeps its underscores.
     */
    public const TYPE_BY_SLUG = [
        'photo' => 'photo',
        'citizenship-front' => 'citizenship_front',
        'citizenship-back' => 'citizenship_back',
        'national-id' => 'national_id',
        'pan' => 'pan',
        'signature' => 'signature',
    ];

    /**
     * @param  string  $slug  one of TYPE_BY_SLUG's keys
     * @param  bool  $download  attachment rather than inline
     */
    public function respond(Profile $profile, string $slug, bool $download = false): BinaryFileResponse
    {
        $documentType = self::TYPE_BY_SLUG[$slug] ?? null;

        abort_unless($documentType !== null, 404);

        $path = $profile->documents()->where('document_type', $documentType)->value('file_path');

        abort_unless(is_string($path) && $path !== '', 404);

        if (! Storage::disk('private')->exists($path)) {
            throw new FileNotFoundException($path);
        }

        $filename = basename($path);
        // Resolved through the disk rather than storage_path() so the served
        // file always comes from wherever the private disk is rooted.
        $absolutePath = Storage::disk('private')->path($path);

        if ($download) {
            return response()->download($absolutePath, $filename);
        }

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
