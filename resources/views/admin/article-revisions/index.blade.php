@extends('layouts.admin')
@section('title','Versioni — '.$article->title)
@section('content')

@include('partials.article-revisions-index', [
  'editUrl' => route('admin.articles.edit', $article),
  'showUrlName' => 'admin.articles.revisions.show',
])

@endsection
