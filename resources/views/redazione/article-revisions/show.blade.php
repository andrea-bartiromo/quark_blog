@extends('layouts.redazione')
@section('title','Versione del '.$revision->created_at->timezone('Europe/Rome')->format('d/m/Y H:i'))
@section('content')

@include('partials.article-revision-comparison', [
  'indexUrlName' => 'redazione.articles.revisions.index',
  'restoreUrl' => route('redazione.articles.revisions.restore', [$article, $revision]),
  'canRestore' => $article->status !== 'published',
  'cannotRestoreReason' => 'Gli articoli pubblicati non possono essere modificati. Contatta l\'editor.',
])

@endsection
