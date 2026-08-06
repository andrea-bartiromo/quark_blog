<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use Illuminate\Http\Request;

class ProjectArticleLinkController extends Controller
{
    public function link(Request $request, Project $project)
    {
        $validated = $request->validate([
            'article_id' => 'required|exists:articles,id',
        ]);

        $article = Article::findOrFail($validated['article_id']);

        // Il vincolo unique su project_article lo impedirebbe comunque a
        // livello di DB; il controllo qui evita un errore SQL per un'azione
        // utente del tutto plausibile (doppio click, articolo gia' collegato).
        if (! $project->articles()->where('articles.id', $article->id)->exists()) {
            $project->articles()->attach($article->id, ['created_by' => auth()->id()]);

            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project_article',
                subjectId: $article->id,
                subjectTitle: $article->title,
                action: 'Articolo collegato: «'.$article->title.'»',
                userId: auth()->id(),
            );
        }

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'articles'])->with('success', 'Articolo collegato.');
    }

    public function unlink(Project $project, Article $article)
    {
        $project->articles()->detach($article->id);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'project_article',
            subjectId: $article->id,
            subjectTitle: $article->title,
            action: 'Articolo scollegato: «'.$article->title.'»',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'articles'])->with('success', 'Articolo scollegato.');
    }
}
