<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>GoodPay Login</title>

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
