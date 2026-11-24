// What to tell the user when a page's AJAX request fails. Takes a failed
// jQuery request (anything with status, statusText and responseJSON) and
// returns { kind, message, retry, errors }.
//
// Only messages written by this application are shown as they are: field
// errors (422) and business refusals, which carry a `code` (bootstrap/app.php
// renders BusinessRuleViolation as {message, code, details}). Other bodies
// are not, because a framework error in local debug mode can carry
// exception details.
export function describeFailure(xhr) {
    const status = Number(xhr?.status ?? 0);
    const body = xhr?.responseJSON !== null && typeof xhr?.responseJSON === 'object' ? xhr.responseJSON : {};
    const errors = body.errors !== null && typeof body.errors === 'object' ? body.errors : {};
    const ownMessage = typeof body.code === 'string' && typeof body.message === 'string' && body.message !== '' ? body.message : null;

    if (status === 0) {
        return failure('network', 'No hubo respuesta del servidor. Revisa tu conexión y vuelve a intentarlo.', true);
    }

    switch (status) {
        case 401:
        case 419:
            return failure('session', 'Tu sesión ha expirado. Inicia sesión de nuevo para continuar.', false);
        case 403:
            return failure('forbidden', ownMessage ?? 'No tienes acceso a esto. Recarga la página para ver qué puedes hacer.', false);
        case 404:
            return failure('not_found', 'No encontrado. Puede que ya no exista, o que ya no tengas acceso a él.', false);
        case 409:
            return failure('conflict', ownMessage ?? 'Esto cambió mientras tanto.', false);
        case 422:
            return failure('validation', 'Revisa los campos marcados.', false, errors);
        case 429:
            return failure('throttled', 'Demasiadas solicitudes. Espera un momento y vuelve a intentarlo.', true);
        default:
            return failure('server', 'Algo salió mal en el servidor. Vuelve a intentarlo en un momento.', true);
    }
}

function failure(kind, message, retry, errors = {}) {
    return { kind, message, retry, errors };
}
