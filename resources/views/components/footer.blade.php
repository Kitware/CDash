@php
use CDash\Config;

$version = Config::getVersion();
@endphp

{{-- Fill the space between the content and the footer to push the footer to the bottom of the page --}}
<div style="flex:1;"></div>

<div id="footer" class="clearfix ng-scope">
    <div id="kitwarelogo">
        <a href="https://www.kitware.com">
            <img src="{{ asset('img/kitware_logo_footer.svg') }}" alt="logo">
        </a>
    </div>

    <div id="footerlinks" class="clearfix">
        <a href="https://www.cdash.org" class="footerlogo">
            <img src="{{ asset('img/cdash.png?rev=2019-05-08') }}" height="30" alt="CDash logo">
        </a>
        <span id="footertext" class="pull-right">
            CDash {{ $version }} ©&nbsp; <a href="https://www.kitware.com">Kitware</a>
            | <a href="https://github.com/Kitware/CDash/issues" target="_blank">Report problems</a>

            @if(isset($angular) && $angular === true)
                | <a ng-href="@{{cdash.endpoint}}">View as JSON</a>
            @elseif(isset($vue) && $vue === true)
                | <a id="api-endpoint">View as JSON</a> {{-- Will be filled by Vue --}}
            @endif

            @if(isset($angular) && $angular === true)
                <span ng-if="::cdash.generationtime"
                      tooltip-popup-delay="1500"
                      tooltip-append-to-body="true"
                      uib-tooltip="Total (Backend API)"
                >
                    | @{{cdash.generationtime}}
                </span>
            @elseif(isset($vue) && $vue === true)
                | <span id="generation-time"></span> {{-- Will be filled by Vue --}}
            @elseif(defined('LARAVEL_START')) {{-- LARAVEL_START might not be defined in the testing environment --}}
                <span>
                    | {{ round(microtime(true) - LARAVEL_START, 2) }}s
                </span>
            @endif

            @if(isset($angular) && $angular === true)
                <span ng-if="::cdash.currentdate">
                    <br>
                    Current Testing Day @{{ ::cdash.currentdate }}
                    | Started at @{{ ::cdash.nightlytime }}
                </span>
            @elseif(isset($vue) && $vue === true)
                <br>
                <span id="testing-day"></span> {{-- Will be filled by Vue --}}
            @endif
        </span>
    </div>
</div>
