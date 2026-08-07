<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;

/**
 * Collega automaticamente un nuovo articolo al "progetto editoriale
 * predefinito" (Blocco F automazione Progettazione), se ne esiste uno
 * attivo. Nessuna euristica su titolo/categoria/autore: l'unico criterio
 * è il flag esplicito Project::is_default_editorial, ammesso solo sui
 * tipi editoriali (mai su progetti tecnici — garantito da
 * Project::defaultEditorial()).
 *
 * Interviene solo alla CREAZIONE dell'articolo, mai sui salvataggi
 * successivi: un collegamento rimosso manualmente in un secondo momento
 * non verrebbe altrimenti mai ripristinato in modo prevedibile, e il
 * collegamento manuale (ProjectArticleLinkController) resta comunque
 * sempre disponibile come prima.
 */
class ProjectEditorialLinkService
{
    public function linkToDefaultProject(Article $article): void
    {
        $project = Project::defaultEditorial();

        if (! $project) {
            return;
        }

        if ($project->articles()->where('articles.id', $article->id)->exists()) {
            return;
        }

        $project->articles()->attach($article->id, ['created_by' => null]);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'project_article',
            subjectId: $article->id,
            subjectTitle: $article->title,
            action: 'Articolo collegato automaticamente al progetto editoriale predefinito: «'.$article->title.'»',
            userId: null,
            source: ProjectActivityLog::SOURCE_SYSTEM,
        );
    }
}
