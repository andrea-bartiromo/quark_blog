@extends('layouts.redazione')
@section('title','Versioni — '.$article->title)
@section('content')

@include('partials.article-revisions-index', [
  'editUrl' => route('redazione.articles.edit', $article),
  'showUrlName' => 'redazione.articles.revisions.show',
])

@endsection
