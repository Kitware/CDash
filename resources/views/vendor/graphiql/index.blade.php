<!DOCTYPE html>
<html lang="en">
@php use MLL\GraphiQL\GraphiQLAsset; @endphp
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GraphiQL</title>
    <style>
        body {
            margin: 0;
            overflow: hidden; /* in Firefox */
        }

        #graphiql {
            height: 100dvh;
        }

        #graphiql-loading {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
    </style>
    <script src="{{ GraphiQLAsset::reactJS() }}"></script>
    <script src="{{ GraphiQLAsset::reactDOMJS() }}"></script>
    <link rel="stylesheet" href="{{ GraphiQLAsset::graphiQLCSS() }}"/>
    <link rel="stylesheet" href="{{ GraphiQLAsset::pluginExplorerCSS() }}"/>
    <link rel="shortcut icon" href="{{ GraphiQLAsset::favicon() }}"/>
</head>

<body>

<div id="graphiql">
    <div id="graphiql-loading">Loading…</div>
</div>

<script src="{{ GraphiQLAsset::graphiQLJS() }}"></script>
<script src="{{ GraphiQLAsset::pluginExplorerJS() }}"></script>
<script>
    // Sends the current CSRF token from the XSRF-TOKEN cookie, which stays current even if the
    // token changes after this page loads. A 419 response refreshes the cookie, so retry once.
    // This mirrors fetchWithCsrfToken() in resources/js/vue/components/shared/CsrfFetch.js.
    async function fetchWithCsrfToken(url, options = {}) {
        const send = () => {
            const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
            const token = cookie === undefined ? '' : decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
            const headers = new Headers(options.headers);
            headers.set('X-XSRF-TOKEN', token);
            return fetch(url, {...options, headers});
        };
        const response = await send();
        return response.status === 419 ? send() : response;
    }

    const fetcher = GraphiQL.createFetcher({
        url: '{{ $url }}',
        subscriptionUrl: '{{ $subscriptionUrl }}',
        fetch: fetchWithCsrfToken,
    });
    const explorer = GraphiQLPluginExplorer.explorerPlugin();

    function GraphiQLWithExplorer() {
        return React.createElement(GraphiQL, {
            fetcher,
            plugins: [
                explorer,
            ],
            // See https://github.com/graphql/graphiql/tree/main/packages/graphiql#props for available settings
        });
    }

    ReactDOM.render(
        React.createElement(GraphiQLWithExplorer),
        document.getElementById('graphiql'),
    );
</script>

</body>
</html>
