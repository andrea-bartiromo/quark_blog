<?php

namespace App\Services\SocialWorkspace;

use RuntimeException;

/**
 * Messaggio sempre leggibile per un utente redazionale, mai un dettaglio
 * tecnico (query SQL, stack trace, eccezione di libreria) — quelli restano
 * nei log applicativi esistenti, mai in questa eccezione.
 */
class SocialDraftValidationException extends RuntimeException {}
