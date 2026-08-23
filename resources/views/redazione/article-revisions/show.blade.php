@extends('layouts.redazione')
@section('title','Versione del '.$revision->created_at->timezone('Europe/Rome')->format('d/m/Y H:i'))
@section('content')

@php
  $revisionHasEditorOnlyStatus = in_array($revision->status, [
      \App\Models\Article::STATUS_SCHEDULED,
      \App\Models\Article::STATUS_PUBLISHED,
  ], true);
  $articleIsPublished = $article->status === \App\Models\Article::STATUS_PUBLISHED;
@endphp

@include('partials.article-revision-comparison', [
  'indexUrlName' => 'redazione.articles.revisions.index',
  'restoreUrl' => route('redazione.articles.revisions.restore', [$article, $revision]),
  'canRestore' => ! $articleIsPublished && ! $revisionHasEditorOnlyStatus,
  'cannotRestoreReason' => $articleIsPublished
      ? 'Gli articoli pubblicati non possono essere modificati. Contatta l\'editor.'
      : 'Questa versione aveva uno stato riservato all\'editor e non può essere ripristinata dalla Redazione.',
])

@endsection
