<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8"/>
        <meta name="csrf-token"
              content="{{ csrf_token() }}">
        <meta http-equiv="X-UA-Compatible"
              content="IE=edge"/>
        <meta name="viewport"
              content="width=device-width, initial-scale=1, maximum-scale=1.0"/>
        <title>{{ config('shopify.app_name', config('app.name')) }}</title>
        <link rel="stylesheet"
              href="https://sdks.shopifycdn.com/polaris/2.6.1/polaris.min.css"/>

    @if(config('app.env') == 'production')
        <!-- Google Tag Manager (noscript) -->
            <noscript>
                <iframe src="https://www.googletagmanager.com/ns.html?id={{ config("analytics.google-tag-manager") }}"
                        height="0"
                        width="0"
                        style="display:none;visibility:hidden"></iframe>
            </noscript>
            <!-- End Google Tag Manager (noscript) -->
        @endif

    </head>
    <body>

        <div class="Polaris-Page Polaris-Page--singleColumn">
            <div class="Polaris-Page__Content">
                <div class="Polaris-Layout">
                    <div class="Polaris-Layout__Section">
                        <div class="Polaris-Banner Polaris-Banner--statusInfo Polaris-Banner--withinPage"
                             tabindex="0"
                             role="status"
                             aria-live="polite"
                             aria-labelledby="Banner1Heading"
                             aria-describedby="Banner1Content">
                            <div class="Polaris-Banner__Ribbon">
                                <span class="Polaris-Icon Polaris-Icon--colorTealDark Polaris-Icon--isColored Polaris-Icon--hasBackdrop">
                                    <svg class="Polaris-Icon__Svg"
                                         viewBox="0 0 20 20"
                                         focusable="false"
                                         aria-hidden="true">
                                    <g fill-rule="evenodd">
                                    <circle cx="10"
                                            cy="10"
                                            r="9"
                                            fill="currentColor">
                                    </circle>
                                    <path d="M10 0C4.486 0 0 4.486 0 10s4.486 10 10 10 10-4.486 10-10S15.514 0 10 0m0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8m1-5v-3a1 1 0 0 0-1-1H9a1 1 0 1 0 0 2v3a1 1 0 0 0 1 1h1a1 1 0 1 0 0-2m-1-5.9a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2">
                                    </path>
                                    </g>
                                    </svg>
                                    </span>
                            </div>
                            <div>
                                <div class="Polaris-Banner__Heading"
                                     id="Banner1Heading">
                                    <p class="Polaris-Heading">{{ config('shopify.app_name') }} is for Shopify and Shopify Plus stores only</p>
                                </div>
                                <div class="Polaris-Banner__Content"
                                     id="Banner1Content">
                                    <p>Shopify is a commerce platform that allows anyone to easily sell online, at a
                                        retail location, and everywhere in between.
                                    </p>
                                    <div class="Polaris-Banner__Actions">
                                        <div class="Polaris-ButtonGroup">
                                            <div class="Polaris-ButtonGroup__Item">
                                                <a target="_blank"
                                                   class="Polaris-Button Polaris-Button--outline"
                                                   href="{{ config('shopify.affiliate_url') }}"
                                                   rel="noopener noreferrer"
                                                   data-polaris-unstyled="true">
                                                    <span class="Polaris-Button__Content">
                                                        <span>Learn more about Shopify</span>
                                                        </span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if (session()->has('error'))
                        <div class="Polaris-Banner Polaris-Banner--statusWarning Polaris-Banner--withinPage" tabindex="0" role="alert" aria-live="polite">
                            <div class="Polaris-Banner__Ribbon"><span class="Polaris-Icon Polaris-Icon--colorYellowDark Polaris-Icon--isColored Polaris-Icon--hasBackdrop"><svg class="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true"><g fill-rule="evenodd"><circle fill="currentColor" cx="10" cy="10" r="9"></circle><path d="M10 0C4.486 0 0 4.486 0 10s4.486 10 10 10 10-4.486 10-10S15.514 0 10 0m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8m0-13a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1m0 8a1 1 0 1 0 0 2 1 1 0 0 0 0-2"></path></g></svg></span></div>
                            <div class="Polaris-Banner__Content" id="Banner37Content">{!! session('error')  !!}</div>
                        </div>
                        @endif
                    </div>
                    <div class="Polaris-Layout__Section">
                        <div class="Polaris-Card">
                            <div class="Polaris-Card__Header">
                                <h2 class="Polaris-Heading">Login or Install {{ config('shopify.app_name') }}</h2>
                            </div>
                            <div class="Polaris-Card__Section">
                                <form  method="POST"
                                        action="{{ route('authenticate', [], false) }}">
                                    {{ csrf_field() }}
                                    <div class="Polaris-FormLayout">
                                        <div class="Polaris-FormLayout__Item">
                                            <div class="">
                                                <div class="Polaris-Labelled__LabelWrapper">
                                                    <div class="Polaris-Label">
                                                        <label id="TextField1Label"
                                                               for="TextField1"
                                                               class="Polaris-Label__Text">Enter your Shopify store's
                                                            URL</label>
                                                    </div>
                                                </div>
                                                <div class="Polaris-TextField">
                                                    <input id="TextField1"
                                                           placeholder="example-shop.myshopify.com"
                                                           class="Polaris-TextField__Input"
                                                           aria-label="Enter your Shopify store's URL"
                                                           aria-labelledby="TextField1Label"
                                                           aria-invalid="false"
                                                           name="shop"
                                                           required
                                                           value="">
                                                    <div class="Polaris-TextField__Backdrop">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="Polaris-FormLayout__Item">
                                            <button type="submit"
                                                    class="Polaris-Button Polaris-Button--primary">
                                                <span class="Polaris-Button__Content">
                                                    <span>Submit</span>
                                                    </span>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="Polaris-VisuallyHidden">
                                    <button type="submit"
                                            aria-hidden="true">
                                    </button>
                                    </span>
                                </form>
                            </div>
                        </div>
                        <div class="Polaris-Card">

                            <div class="Polaris-Card__Header">
                                <h2 class="Polaris-Heading">What is {{ config('shopify.app_name') }}?</h2>
                            </div>
                            <div class="Polaris-Card__Section">
                            @if($shopifyApp)
                                {{ $shopifyApp['description'] }}
                            @else
                                {!! config('shopify.app_description') !!}
                            @endif
                            </div>
                            <div class="Polaris-Card__Footer">
                                <div class="Polaris-ButtonGroup">
                                    <div class="Polaris-ButtonGroup__Item">
                                        <a target="_blank" class="Polaris-Button Polaris-Button--primary" href="{{ config('shopify.demo_url') }}" rel="noopener noreferrer" data-polaris-unstyled="true">
                                            <span class="Polaris-Button__Content"><span>View demo</span></span>
                                        </a>
                                    </div>
                                    <div class="Polaris-ButtonGroup__Item Polaris-ButtonGroup__Item--plain">
                                        <a target="_blank" class="Polaris-Button Polaris-Button--plain" href="https://apps.shopify.com/{{config('shopify.app_slug')}}" rel="noopener noreferrer" data-polaris-unstyled="true"><span class="Polaris-Button__Content"><span>Visit app store</span></span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
