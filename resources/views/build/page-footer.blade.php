@php
use CDash\Config;
$version = Config::getVersion();
@endphp
<page-footer
    version="{{ $version }}"
    cdash-logo="{{ asset('img/cdash.png?rev=2019-05-08') }}"
    kitware-logo="{{ asset('img/kitware_logo_footer.png') }}"
></page-footer>
