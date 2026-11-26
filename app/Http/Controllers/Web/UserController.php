<?php

namespace App\Http\Controllers\Web;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangeUserStatusRequest;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * User administration, admin only (Fase 4, docs/sdd/fase-4-usuarios-admin/).
 * Thin controller: the Form Requests validate and authorize, and
 * UserAccountService applies the rules (scope, audit, token/session revocation).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $role = $request->query('role');
        $status = $request->query('status');
        $company = $request->query('company');
        $station = $request->query('station');
        $term = trim($request->string('q')->toString());

        $users = User::query()
            ->with(['company', 'station'])
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->when(in_array($role, array_column(UserRole::cases(), 'value'), true), fn ($query) => $query->where('role', $role))
            ->when($status === 'active' || $status === 'inactive', fn ($query) => $query->where('is_active', $status === 'active'))
            ->when(is_string($company) && ctype_digit($company), fn ($query) => $query->where('company_id', (int) $company))
            ->when(is_string($station) && ctype_digit($station), fn ($query) => $query->where('station_id', (int) $station))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'filters' => [
                'q' => $term,
                'role' => is_string($role) ? $role : '',
                'status' => is_string($status) ? $status : '',
                'company' => is_string($company) ? $company : '',
                'station' => is_string($station) ? $station : '',
            ],
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id'),
            'stations' => Station::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', $this->formData(null));
    }

    public function store(StoreUserRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $accounts->create(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
            $request->role(),
            $request->company(),
            $request->station(),
            $this->actor($request),
        );

        return redirect()->route('users.index')
            ->with('status', "Cuenta {$user->email} creada. Debe cambiar la contraseña al entrar.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', $this->formData($user));
    }

    public function update(UpdateUserRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->update($user, [
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'role' => $request->role(),
            'company' => $request->company(),
            'station' => $request->station(),
        ], $this->actor($request));

        return redirect()->route('users.index')->with('status', "Cuenta {$user->email} guardada.");
    }

    public function updateStatus(ChangeUserStatusRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        if ($request->boolean('is_active')) {
            $accounts->activate($user, $this->actor($request));
            $status = 'activada';
        } else {
            $accounts->deactivate($user, $this->actor($request));
            $status = 'desactivada';
        }

        return back()->with('status', "La cuenta {$user->email} quedó {$status}.");
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->resetPassword($user, (string) $request->validated('password'), $this->actor($request));

        return back()->with('status', "Se asignó una contraseña temporal a {$user->email}; deberá cambiarla al entrar.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?User $user): array
    {
        return [
            'user' => $user,
            'roles' => UserRole::cases(),
            'companies' => Company::query()->where('status', CompanyStatus::Active->value)->orderBy('name')->pluck('name', 'id'),
            'stations' => Station::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return $actor;
    }
}
