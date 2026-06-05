<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>GoodPay Login</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" href="/favicon-32x32.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <style>
            :root {
                --legacy-menu-bg: url("{{ asset('images/menu_bg_gradient.png') }}");
            }

            @font-face {
                font-family: "chinese_takeawayregular";
                font-style: normal;
                font-weight: 400;
                src:
                    url("{{ asset('fonts/chinesetakeaway-webfont.woff2') }}") format("woff2"),
                    url("{{ asset('fonts/chinesetakeaway-webfont.woff') }}") format("woff");
            }
        </style>

        @vite(['resources/css/app.css'])
    </head>
    <body class="kassa-login-body">
        <header class="kassa-menu-bar">
            <img id="logo" src="/images/goodpay.png" alt="GoodPay logo">
        </header>

        <main id="loginDiv">
            <form action="{{ route('login.store') }}" method="POST">
                @csrf

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="E-mailadres"
                    autocomplete="username"
                    autofocus
                ><br>

                <input
                    type="password"
                    name="password"
                    placeholder="Wachtwoord"
                    autocomplete="current-password"
                ><br>

                <input type="submit" value="inloggen"><br>
            </form>
        </main>

        @if ($errors->any())
            <div class="errorMessage">{{ $errors->first() }}</div>
        @endif
    </body>
</html>
