<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Exports\OrganizationExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationExportController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        OrganizationExportService $exports,
    ): StreamedResponse {
        abort_unless($organization->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'format' => ['required', 'string', 'in:'.implode(',', $exports->formats())],
        ]);

        $format = $validated['format'];
        $content = $exports->build($organization, $format);

        return response()->streamDownload(
            static fn () => print $content,
            $exports->filename($organization, $format),
            ['Content-Type' => $exports->contentType($format)],
        );
    }
}
