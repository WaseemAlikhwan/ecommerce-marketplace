<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? config('app.name') }}</title>
@isset($description)
    <meta name="description" content="{{ $description }}">
@endisset
@isset($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endisset
<meta name="robots" content="{{ $robots ?? 'noindex,follow' }}">
@isset($ogTitle)
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription ?? $description ?? '' }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:url" content="{{ $ogUrl ?? $canonical ?? url()->current() }}">
    @if (! empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
@endisset
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700|ibm-plex-sans-arabic:400,500,600,700" rel="stylesheet" />
@vite(['resources/css/app.css', 'resources/js/app.js'])
