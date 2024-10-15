{{-- Fill the space between the content and the footer to push the footer to the bottom of the page --}}
<div style="flex:1;"></div>

<div id="footer" class="ng-scope">
    <div>
        <a href="https://www.kitware.com">
            <img src="{{ asset('img/kitware_logo_footer.svg') }}" alt="logo" height="30" style="height: 30px;">
        </a>
    </div>
    <div class="footer-element" style="text-align: right;">
        <a href="https://www.cdash.org">
            <img src="{{ asset('img/cdash_logo_full.svg?rev=2023-05-31') }}" height="30" alt="CDash logo" style="height: 30px; display: inherit;">
        </a>
        <span>
            {{ $cdash_version }}
        </span>
        |
        <a class="footer-link" href="https://github.com/Kitware/CDash/issues" target="_blank">Report&nbsp;Problems</a>
        @if(isset($angular) && $angular === true)
            | <a class="footer-link" ng-href="@{{cdash.endpoint}}">View&nbsp;as&nbsp;JSON</a> {{-- All AngularJS pages have a JSON link --}}
        @elseif(isset($vue) && $vue === true)
            <span id="api-endpoint-container" style="display: none;"> {{-- Will be displayed by Vue (if applicable) --}}
                | <a class="footer-link" id="api-endpoint">View as JSON</a>
            </span>
        @endif
        |
        <a href="{{ url('/graphql/explorer') }}" class="footer-link">
            GraphQL&nbsp;Explorer
        </a>
        @if(isset($angular) && $angular === true)
            <span ng-if="::cdash.currentdate">
                | Testing day @{{ ::cdash.currentdate }} started at @{{ ::cdash.nightlytime }}
            </span>
        @elseif(isset($vue) && $vue === true)
            <span id="testing-day"></span> {{-- Will be filled by Vue --}}
        @endif
        |
        <span id="generation-time"> {{-- Content gets overwritten by Vue deliberately on some legacy pages --}}
            @if(isset($angular) && $angular === true)
                @{{cdash.generationtime}}
            @else
                {{ round(microtime(true) - LARAVEL_START, 2) }}s
            @endif
        </span>
    </div>
</div>
