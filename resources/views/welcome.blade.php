<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Webkit API</title>

    <!-- Fonts -->
    <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Styles -->
    <style>
        /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */
        html {
            line-height: 1.15;
            -webkit-text-size-adjust: 100%
        }

        body {
            margin: 0
        }

        a {
            background-color: transparent
        }

        [hidden] {
            display: none
        }

        html {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
            line-height: 1.5
        }

        *, :after, :before {
            box-sizing: border-box;
            border: 0 solid #e2e8f0
        }

        a {
            color: inherit;
            text-decoration: inherit
        }

        svg, video {
            display: block;
            vertical-align: middle
        }

        video {
            max-width: 100%;
            height: auto
        }

        .bg-gray-100 {
            --tw-bg-opacity: 1;
            background-color: rgb(243 244 246 / var(--tw-bg-opacity))
        }

        .flex {
            display: flex
        }

        .justify-center {
            justify-content: center
        }

        .max-w-6xl {
            max-width: 72rem
        }

        .min-h-screen {
            min-height: 100vh
        }

        .py-4 {
            padding-top: 1rem;
            padding-bottom: 1rem
        }

        .relative {
            position: relative
        }

        .text-center {
            text-align: center
        }

        .antialiased {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale
        }

        @media (min-width: 640px) {
            .sm\:items-center {
                align-items: center
            }

            .sm\:pt-0 {
                padding-top: 0
            }
        }

        @media (prefers-color-scheme: dark) {
            .dark\:bg-gray-900 {
                --tw-bg-opacity: 1;
                background-color: rgb(17 24 39 / var(--tw-bg-opacity))
            }

            .dark\:text-white {
                --tw-text-opacity: 1;
                color: rgb(255 255 255 / var(--tw-text-opacity))
            }
        }
    </style>

    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
    </style>
</head>
<body class="antialiased">
<div class="relative flex justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
    <div class="text-center max-w-6xl">
        <h1 class="dark:text-white">Webkit API</h1>
        <p class="dark:text-white">
            The Webkit API provides a foundation for building web applications within this project.
            It leverages the capabilities of the Laravel framework, and implements core functionalities
            needed by web applications using industry best practices. This API serves as a starting point
            for developers.
        </p>
        <p class="dark:text-white">
            <a style="text-decoration-line: underline" href="{{ route('docs-page') }}" target="_blank">
                Visit the Documentation Page
            </a>
        </p>
    </div>
</div>
</body>
</html>
