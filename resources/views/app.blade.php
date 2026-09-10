<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#285d49">
    <meta name="description" content="Planejamento alimentar, receitas e composição nutricional com fontes rastreáveis.">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <title>Nutria</title>
    @vite('resources/js/app.js')
</head>
<body><div
    id="app"
    data-user="{{ json_encode(auth()->user()?->only([
        'id',
        'name',
        'email',
        'role',
        'status'
    ])) }}"
    data-admin-setup="{{ ($adminSetup ?? false) ? '1' : '0' }}"
    data-admin-email="{{ $adminEmail ?? '' }}"
></div><noscript>Ative o JavaScript para acessar seu planejamento alimentar.</noscript></body>
</html>
