@extends('web.layout')

@section('title', '首页')

@section('content')
<x-sidebar.hot-author class="mb-8" />

<x-sidebar.hot-poem />
@endsection

@section('sidebar')
<x-sidebar.hot-tag class="mb-8" />

<x-sidebar.hot-book />

@endsection