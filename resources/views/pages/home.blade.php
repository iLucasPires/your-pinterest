@extends('layouts.base')

@section('title', 'Encontre sua galeria')

@section('description', 'Encontre e acesse sua galeria de fotos.')

@section('content')
    <main class="relative isolate min-h-screen overflow-hidden bg-taupe-50 px-4 text-zinc-950 dark:bg-zinc-950 dark:text-white sm:px-6 lg:px-8">
        <x-home.header />
        <x-home.hero />
        <x-home.recent-gallery :recent-galleries="$recentGalleries" />
    </main>
@endsection
