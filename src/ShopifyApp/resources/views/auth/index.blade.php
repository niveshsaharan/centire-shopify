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

    <script src="https://wchat.freshchat.com/js/widget.js"></script>
    <script>
        window.Env = <?php echo json_encode([
            'app_slug' => config('shopify.app_slug'),
            'freshdesk' => [
                'chat' => [
                    'token' => config('freshdesk.chat.token'),
                    'host' => config('freshdesk.chat.host'),
                    'site_id' => config('freshdesk.chat.site_id'),
                ],
            ],
        ]); ?>
    </script>

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
                        @if(request('type') !== 'private')
                        <div class="Polaris-Banner__Heading"
                             id="Banner1Heading">
                            <p class="Polaris-Heading">{{ config('shopify.app_name') }} is for Shopify and
                                Shopify Plus stores only
                            </p>
                        </div>
                        @endif
                        <div class="Polaris-Banner__Content"
                             id="Banner1Content">
                            @if(request('type') === 'private')
                                <p>We have requested Shopify to have another look on their decision of removing our apps from the app store. While we are waiting for their response, We have made the app completely free to use till September 30th, 2020</p>
                            @else
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
                            @endif
                        </div>
                    </div>
                </div>
                @if (session()->has('error'))
                    <div class="Polaris-Banner Polaris-Banner--statusWarning Polaris-Banner--withinPage"
                         tabindex="0"
                         role="alert"
                         aria-live="polite">
                        <div class="Polaris-Banner__Ribbon">
                                    <span class="Polaris-Icon Polaris-Icon--colorYellowDark Polaris-Icon--isColored Polaris-Icon--hasBackdrop"><svg
                                                class="Polaris-Icon__Svg"
                                                viewBox="0 0 20 20"
                                                focusable="false"
                                                aria-hidden="true"><g fill-rule="evenodd"><circle fill="currentColor"
                                                                                                  cx="10"
                                                                                                  cy="10"
                                                                                                  r="9"></circle><path
                                                        d="M10 0C4.486 0 0 4.486 0 10s4.486 10 10 10 10-4.486 10-10S15.514 0 10 0m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8m0-13a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1m0 8a1 1 0 1 0 0 2 1 1 0 0 0 0-2"></path></g></svg></span>
                        </div>
                        <div class="Polaris-Banner__Content"
                             id="Banner37Content">{{session('error')}}</div>
                    </div>
                @endif
            </div>
            <div class="Polaris-Layout__Section">
                <div class="Polaris-Card">
                    <div class="Polaris-Card__Header">
                        <h2 class="Polaris-Heading">Login or Install {{ config('shopify.app_name') }}</h2>
                    </div>
                    <form method="POST"
                          action="{{ route('authenticate', [], false) }}">
                        <div class="Polaris-Card__Section">
                            {{ csrf_field() }}
                            <div class="Polaris-FormLayout">
                                <div class="Polaris-FormLayout__Item">
                                    <div class="">
                                        <div class="Polaris-Labelled__LabelWrapper">
                                            <div class="Polaris-Label">
                                                <label
                                                        for="shop"
                                                        class="Polaris-Label__Text">Enter your Shopify store's
                                                    URL</label>
                                            </div>
                                        </div>
                                        <div class="Polaris-TextField">
                                            <input id="shop"
                                                   placeholder="example-shop.myshopify.com"
                                                   class="Polaris-TextField__Input"
                                                   aria-label="Enter your Shopify store's URL"
                                                   aria-labelledby="TextField1Label"
                                                   aria-invalid="false"
                                                   name="shop"
                                                   required
                                                   value="{{old('shop')}}">
                                            <div class="Polaris-TextField__Backdrop">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if(request('type') === 'private')
                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="password"
                                                            class="Polaris-Label__Text">Set a password to login next
                                                        time with</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="password"
                                                       placeholder="Choose a strong password"
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Set a password to login next time with"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="password"
                                                       type="password"
                                                       required
                                                       minlength="6"
                                                       value="{{old('password')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="confirm_password"
                                                            class="Polaris-Label__Text">Re-enter your password</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="confirm_password"
                                                       placeholder="Confirm password"
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Re-enter your password"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="confirm_password"
                                                       type="password"
                                                       required
                                                       minlength="6"
                                                       value="{{old('confirm_password')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="api_key"
                                                            class="Polaris-Label__Text">Your Private API Key (Copy from
                                                        Shopify admin private app)</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="api_key"
                                                       placeholder=""
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Your Private API Key"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="api_key"
                                                       required
                                                       value="{{old('api_key')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="api_password"
                                                            class="Polaris-Label__Text">Your Private API Password (Copy
                                                        from Shopify admin private app)</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="api_password"
                                                       placeholder=""
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Your Private API Password"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="api_password"
                                                       required
                                                       value="{{old('api_password')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="api_secret"
                                                            class="Polaris-Label__Text">Your Private API Shared Secret
                                                        (Copy from Shopify admin private app)</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="api_secret"
                                                       placeholder=""
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Your Private API Secret Key"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="api_secret"
                                                       required
                                                       value="{{old('api_secret')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="Polaris-FormLayout__Item">
                                        <div class="">
                                            <div class="Polaris-Labelled__LabelWrapper">
                                                <div class="Polaris-Label">
                                                    <label
                                                            for="password"
                                                            class="Polaris-Label__Text">Your password</label>
                                                </div>
                                            </div>
                                            <div class="Polaris-TextField">
                                                <input id="password"
                                                       placeholder="Your choosen password here"
                                                       class="Polaris-TextField__Input"
                                                       aria-label="Your password"
                                                       aria-labelledby="TextField1Label"
                                                       aria-invalid="false"
                                                       name="password"
                                                       type="password"
                                                       minlength="6"
                                                       value="{{old('password')}}">
                                                <div class="Polaris-TextField__Backdrop">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <span class="Polaris-VisuallyHidden">
                                    <button type="submit"
                                            aria-hidden="true">
                                    </button>
                                    </span>

                            <input type="hidden"
                                   name="ref"
                                   value="{{ request()->get('ref') ? request()->get('ref') : session('__referrer') }}"/>

                        </div>

                        <div class="Polaris-Card__Footer">
                            <div class="Polaris-ButtonGroup">
                                <div class="Polaris-ButtonGroup__Item Polaris-ButtonGroup__Item--plain">
                                    @if(request('type') === 'private')
                                        <a class="Polaris-Button Polaris-Button--plain"
                                           href="{{ route('authenticate', [], false) }}"
                                           rel="noopener noreferrer"
                                           data-polaris-unstyled="true"><span class="Polaris-Button__Content"><span>Click here if you already have setup the password?</span></span>
                                        </a>
                                    @else
                                        <a class="Polaris-Button Polaris-Button--plain"
                                           href="{{ route('authenticate', ['type' => 'private'], false) }}"
                                           rel="noopener noreferrer"
                                           data-polaris-unstyled="true"><span class="Polaris-Button__Content"><span>Click here to setup integration for your private app?</span></span>
                                        </a>
                                    @endif
                                </div>
                                <div class="Polaris-ButtonGroup__Item">
                                    <button type="submit"
                                            class="Polaris-Button Polaris-Button--primary">
                                                <span class="Polaris-Button__Content">
                                                    <span> @if(request('type') === 'private')
                                                            Setup
                                                        @else
                                                            Login
                                                        @endif
                                                    </span>
                                                    </span>
                                    </button>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>

                @if(request('type') === 'private')
                    <div class="Polaris-Card">

                        <div class="Polaris-Card__Header">
                            <h2 class="Polaris-Heading">How to setup a Private App?</h2>
                        </div>
                        <div class="Polaris-Card__Section">
                            Shopify has removed our apps from the app store and we have appealed to them to reconsider their decision. In the meantime, we have built the integration to keep
                            our apps alive with all the same functionalities so you can use our apps as you were. It
                            requires a few additional steps on your side to set it up.
                        </div>
                        <div class="Polaris-Card__Footer">
                            <div class="Polaris-ButtonGroup">
                                <div class="Polaris-ButtonGroup__Item">
                                    <a target="_blank"
                                       class="Polaris-Button Polaris-Button--primary"
                                       href="https://www.notion.so/sellify/The-app-is-not-available-on-Shopify-App-Store-how-do-I-login-install-it-on-my-store-456d4bc66f4443429dc45cb998a35581"
                                       rel="noopener noreferrer"
                                       data-polaris-unstyled="true">
                                        <span class="Polaris-Button__Content"><span>View step by step guide</span></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @else

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
                                <a target="_blank"
                                   class="Polaris-Button Polaris-Button--primary"
                                   href="{{ config('shopify.demo_url') }}"
                                   rel="noopener noreferrer"
                                   data-polaris-unstyled="true">
                                    <span class="Polaris-Button__Content"><span>View demo</span></span>
                                </a>
                            </div>
                            <div class="Polaris-ButtonGroup__Item Polaris-ButtonGroup__Item--plain">
                                <a target="_blank"
                                   class="Polaris-Button Polaris-Button--plain"
                                   href="https://apps.shopify.com/{{config('shopify.app_slug')}}"
                                   rel="noopener noreferrer"
                                   data-polaris-unstyled="true"><span class="Polaris-Button__Content"><span>Visit app store</span></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
    window.fcWidget.init({
        token: window.Env.freshdesk.chat.token,
        host: window.Env.freshdesk.chat.host,
        siteId: window.Env.freshdesk.chat.site_id,
        tags: [window.Env.app_slug],
    });
</script>
</body>
</html>
