<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sollevata da ProjectTask quando si tenta di impostare depends_on_task_id
 * su un valore che renderebbe il grafo delle dipendenze non valido
 * (auto-dipendenza, ciclo diretto o indiretto, dipendenza cross-project).
 *
 * Un'eccezione, non una coercizione silenziosa: a differenza di un flag
 * booleano "al più uno attivo" (dove spegnere gli altri è un default
 * ragionevole), non esiste un valore di ripiego ovvio per un grafo di
 * dipendenze non valido — il chiamante deve saperlo, non vedere il proprio
 * input silenziosamente scartato.
 */
class InvalidTaskDependencyException extends RuntimeException {}
