<?php

namespace App\Http\Controllers\Web;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\BulkImportService;
use App\Support\CsvImport\CsvTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Bulk CSV import screen (docs/sdd/fase-2-importacion-notificaciones/spec.md,
 * A2): one page with a tab per type, each with its downloadable template and
 * an upload form. Admins choose the company; a manager's own company is used.
 * The work is done by BulkImportService; the result is shown in the page after
 * a redirect, so refreshing does not import again.
 */
class ImportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->actor($request);

        return view('import.index', [
            'types' => [
                CsvTemplate::VEHICLES => 'Vehículos',
                CsvTemplate::DRIVERS => 'Conductores',
                CsvTemplate::CARDS => 'Tarjetas',
            ],
            'headers' => [
                CsvTemplate::VEHICLES => implode(',', CsvTemplate::header(CsvTemplate::VEHICLES)),
                CsvTemplate::DRIVERS => implode(',', CsvTemplate::header(CsvTemplate::DRIVERS)),
                CsvTemplate::CARDS => implode(',', CsvTemplate::header(CsvTemplate::CARDS)),
            ],
            'companies' => $user->isAdmin()
                ? Company::query()->where('status', CompanyStatus::Active->value)->orderBy('name')->pluck('name', 'id')
                : null,
            'ownCompany' => $user->isAdmin() ? null : $user->loadMissing('company')->company,
            'result' => session('import_result'),
        ]);
    }

    public function template(string $type): Response
    {
        Gate::authorize('importData');

        abort_unless(CsvTemplate::isType($type), 404);

        return response(CsvTemplate::csv($type), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.CsvTemplate::filename($type).'"',
        ]);
    }

    public function store(ImportRequest $request, string $type, BulkImportService $imports): RedirectResponse
    {
        abort_unless(CsvTemplate::isType($type), 404);

        $file = $request->file('file');
        $contents = $file === null ? '' : (string) file_get_contents($file->getPathname());

        $result = $imports->import(
            $type,
            $request->company(),
            $this->actor($request),
            $request->boolean('dry_run'),
            csv: $contents,
        );

        return redirect()->route('import.index')
            ->with('import_result', $result->toArray())
            ->with('status', $result->dryRun
                ? 'Simulación completada: no se guardó ningún registro.'
                : "Importación completada: {$result->successful} fila(s) guardada(s).");
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
