@extends('layouts.base')

@section('title', $gallery->name)

@section('description', $gallery->description ?? '')

@section('content')

    <livewire:gallery.viewer :gallery-id="$gallery->id" />

    @unless ($hasAccess)
        <div x-data="{ open: false }" x-init="$nextTick(() => {
            open = true
            $flux.modal('gallery-access').show()
        })">
            <div x-show="open" x-transition class="fixed inset-0 z-40 bg-black/20 backdrop-blur-sm"></div>

            <x-gallery.acesse-modal :gallery="$gallery" />
        </div>
    @endunless

@endsection
