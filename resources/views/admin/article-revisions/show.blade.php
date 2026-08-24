@extends('layouts.admin')
@section('title','Versione del '.$revision->created_at->timezone('Europe/Rome')->format('d/m/Y H:i'))
@section('content')

@include('partials.article-revision-comparison', [
  'indexUrlName' => 'admin.articles.revisions.index',
  'restoreUrl' => route('admin.articles.revisions.restore', [$article, $revision]),
  'canRestore' => true,
  'cannotRestoreReason' => '',
])

@endsection
