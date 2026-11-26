<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A signed-in user changes their own password. This is also the screen the
 * forced-change middleware sends a user to after an admin reset it (Fase 4, D7).
 */
class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('password.change');
    }

    public function update(ChangePasswordRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $accounts->changeOwnPassword(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
        );

        return redirect()->route($user->role->homeRoute())->with('status', 'Contraseña actualizada.');
    }
}
