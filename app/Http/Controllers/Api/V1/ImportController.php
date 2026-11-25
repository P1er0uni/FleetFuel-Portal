<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportRequest;
use App\Models\User;
use App\Services\BulkImportService;
use App\Support\CsvImport\CsvTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bulk CSV import for the API (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * D8): POST /api/v1/import/{type} accepts a multipart "file" or a JSON "rows"
 * array; GET /api/v1/import/templates/{type} returns the template. Writes go
 * through BulkImportService, the same service the web screen uses.
 */
class ImportController extends Controller
{
    public function store(ImportRequest $request, string $type, BulkImportService $imports): JsonResponse
    {
        abort_unless(CsvTemplate::isType($type), 404);

        $user = $request->user();
        assert($user instanceof User);

        $file = $request->file('file');
        $result = $file !== null
            ? $imports->import($type, $request->company(), $user, $request->boolean('dry_run'), csv: (string) file_get_contents($file->getPathname()))
            : $imports->import($type, $request->company(), $user, $request->boolean('dry_run'), rows: (array) $request->input('rows', []));

        return response()->json(['data' => $result->toArray()]);
    }

    public function template(Request $request, string $type): Response
    {
        abort_unless(CsvTemplate::isType($type), 404);

        return response(CsvTemplate::csv($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.CsvTemplate::filename($type).'"',
        ]);
    }
}
