<title>CDash - {{ $title }}</title>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="robots" content="noindex,nofollow" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<link rel="shortcut icon" href="favicon.ico" />

<!--[if IE]>
<script language="javascript" type="text/javascript" src="js/excanvas.js">
</script>
<![endif]-->

<script src="js/CDash_{{ $js_version  }}.min.js"></script>
<script src="js/tooltip.js" type="text/javascript" charset="utf-8"></script>
<script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
<script src="js/jquery.dataTables.min.js" type="text/javascript" charset="utf-8"></script>
<script src="js/jquery.metadata.js" type="text/javascript" charset="utf-8"></script>

<link rel="stylesheet" type="text/css" href="css/jquery.dataTables.css" />
<link rel="stylesheet" type="text/css" href="css/cdash.css" />
<link rel="stylesheet" type="text/css" href="{{ asset(mix('build/css/3rdparty.css')) }}" />
