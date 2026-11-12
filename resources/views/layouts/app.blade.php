<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#e9ecef">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FarmaViva">
    <title>@yield('title', 'FarmaViva — Gestão de Escala 5x2')</title>

    <!-- Ícone na Aba do Navegador (Favicon) e Atalhos na Tela Inicial de Dispositivos Móveis -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/logofarmaviver.svg') }}">
    <link rel="shortcut icon" href="{{ asset('img/logofarmaviver.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logofarmaviver.svg') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">

    <!-- Bootstrap 5.3 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Estilos Mobile First em Tons de Cinza Claro -->
    <link href="{{ asset('css/farmaviva.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="d-flex flex-column min-vh-100">

    @yield('header')

    <main class="flex-grow-1">
        @yield('content')
    </main>

    <footer class="py-2 text-center text-secondary fv-footer border-top bg-light mt-auto">
        <div class="container">
            hhernani@gmail.com / 92-98161-4980 - Todos os direitos reservados.
        </div>
    </footer>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Script Principal FarmaViva (Compilado do TypeScript) -->
    <script src="{{ asset('js/farmaviva.js') }}"></script>
    @stack('scripts')
</body>
</html>
