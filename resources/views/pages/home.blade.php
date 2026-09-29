@extends('layouts.base')

@section('title', 'Encontre sua galeria')

@section('description', 'Encontre e acesse sua galeria de fotos.')

@section('content')
    <main class="relative isolate min-h-screen overflow-hidden bg-stone-50 dark:bg-zinc-950">
        <x-home.header />
        <x-home.hero
            :recent-galleries="$recentGalleries"
            :public-photos="$publicPhotos"
        />
    </main>
@endsection
