@php
use CDash\Config;

$version = Config::getVersion();
@endphp
<div id="footer" class="clearfix ng-scope">
    <div id="kitwarelogo">
        <a href="http://www.kitware.com">
            <img src="{{ asset('img/kitware_logo_footer.png') }}" border="0" alt="logo">
        </a>
    </div>
    <div id="footerlinks" class="clearfix">
        <a href="http://www.cdash.org" class="footerlogo">
            <img src="{{ asset('img/cdash.png?rev=2019-05-08') }}" border="0" height="30" alt="CDash logo">
        </a>
        <span id="footertext">
    CDash
   {{ $version }} ©&nbsp;<a href="http://www.kitware.com">Kitware</a>
   | <a href="https://github.com/Kitware/CDash/issues" target="blank">Report problems</a></span>
    </div>
</div>
