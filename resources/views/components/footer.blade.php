{{-- Fill the space between the content and the footer to push the footer to the bottom of the page --}}
<div style="flex:1;"></div>

<div id="footer" class="clearfix ng-scope">
    <div id="kitwarelogo">
        <a class="cdash-link" href="https://www.kitware.com">
            <img src="{{ asset('img/kitware_logo_footer.svg') }}" alt="logo" height="30">
        </a>
    </div>

    <div id="footerlinks" class="clearfix">
        <a href="https://www.cdash.org" class="footerlogo cdash-link">
            <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="30" alt="CDash logo">
        </a>
        <span id="footertext" class="pull-right">
            CDash {{ $cdash_version }} ©&nbsp; <a class="cdash-link" href="https://www.kitware.com">Kitware</a>
            | <a class="cdash-link" href="https://github.com/Kitware/CDash/issues" target="_blank">Report problems</a>

            @if(isset($angular) && $angular === true)
                | <a class="cdash-link" ng-href="@{{cdash.endpoint}}">View as JSON</a>
            @elseif(isset($vue) && $vue === true)
                | <a class="cdash-link" id="api-endpoint">View as JSON</a> {{-- Will be filled by Vue --}}
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
            @else
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
