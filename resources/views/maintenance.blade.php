<!DOCTYPE html>
<html>
<head>
    <title>CDash</title>
    <style>
        body {
            font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
            margin: 0;
        }

        #content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            align-items: center;
            height: 100vh;
        }
    </style>
</head>
<body>
    <div id="content">
        <div>
            <img
                src="data:image/svg+xml;charset=utf-8,{!! rawurlencode(file_get_contents(public_path('/img/cdash_logo_full.svg'))) !!}"
                height="100"
                alt="CDash Logo"
            >
        </div>
        <div>
            CDash is being updated.<br>
            This page will reload automatically when CDash is back online.
        </div>
    </div>
</body>
</html>
