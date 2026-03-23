<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Laravel API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.9.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.9.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="搜索">
    </div>

    <div id="toc">
                    <ul id="tocify-header-" class="tocify-header">
                <li class="tocify-item level-1" data-unique="">
                    <a href="#">介绍</a>
                </li>
                            </ul>
                    <ul id="tocify-header-" class="tocify-header">
                <li class="tocify-item level-1" data-unique="">
                    <a href="#">身份验证请求</a>
                </li>
                            </ul>
                    <ul id="tocify-header-" class="tocify-header">
                <li class="tocify-item level-1" data-unique="">
                    <a href="#">用户认证</a>
                </li>
                                    <ul id="tocify-subheader-" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-login">
                                <a href="#-POSTapi-v1-auth-login">用户登录 (昵称/邮箱/手机号 + 密码)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-register">
                                <a href="#-POSTapi-v1-auth-register">用户注册</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-GETapi-v1-auth--provider--redirect">
                                <a href="#-GETapi-v1-auth--provider--redirect">重定向至第三方登录 (OAuth)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-GETapi-v1-auth--provider--callback">
                                <a href="#-GETapi-v1-auth--provider--callback">处理第三方登录回调</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-password-request">
                                <a href="#-POSTapi-v1-auth-password-request">请求重置密码邮件</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-GETapi-v1-auth-password-confirm">
                                <a href="#-GETapi-v1-auth-password-confirm">确认并执行密码重置</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-GETapi-v1-auth-me">
                                <a href="#-GETapi-v1-auth-me">获取当前认证用户信息</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-logout">
                                <a href="#-POSTapi-v1-auth-logout">退出登录</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-refresh">
                                <a href="#-POSTapi-v1-auth-refresh">刷新访问令牌 (Token)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="-POSTapi-v1-auth-profile">
                                <a href="#-POSTapi-v1-auth-profile">更新用户个人资料</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">查看 Postman 集合</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">查看 OpenAPI 规范</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: March 23, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="">介绍</h1>
<aside>
    <strong>基础 URL</strong>: <code>http://localhost:8000</code>
</aside>
<pre><code>This documentation aims to provide all the information you need to work with our API.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="">身份验证请求</h1>
<p>此 API 不需要认证。</p>

        <h1 id="">用户认证</h1>

    

                                <h2 id="-POSTapi-v1-auth-login">用户登录 (昵称/邮箱/手机号 + 密码)</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-login">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/login" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"nickname\": \"architecto\",
    \"password\": \"]|{+-0pBNvYg\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/login"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "nickname": "architecto",
    "password": "]|{+-0pBNvYg"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-login">
</span>
<span id="execution-results-POSTapi-v1-auth-login" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-login"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-login"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-login" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-login">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-login" data-method="POST"
      data-path="api/v1/auth/login"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-login', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-login"
                    onclick="tryItOut('POSTapi-v1-auth-login');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-login"
                    onclick="cancelTryOut('POSTapi-v1-auth-login');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-login"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/login</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-login"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>正文参数</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nickname</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nickname"                data-endpoint="POSTapi-v1-auth-login"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-login"
               value="]|{+-0pBNvYg"
               data-component="body">
    <br>
<p>value 至少为 6 个字符。. Example: <code>]|{+-0pBNvYg</code></p>
        </div>
        </form>

                    <h2 id="-POSTapi-v1-auth-register">用户注册</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-register">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/register" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"password\": \"+-0pBNvYgxwmi\\/#iw\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/register"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "password": "+-0pBNvYgxwmi\/#iw"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-register">
</span>
<span id="execution-results-POSTapi-v1-auth-register" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-register"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-register" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-register">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-register" data-method="POST"
      data-path="api/v1/auth/register"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-register', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-register"
                    onclick="tryItOut('POSTapi-v1-auth-register');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-register"
                    onclick="cancelTryOut('POSTapi-v1-auth-register');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-register"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>正文参数</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-register"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>value 不是一个合法的邮箱。. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-auth-register"
               value="+-0pBNvYgxwmi/#iw"
               data-component="body">
    <br>
<p>value 至少为 6 个字符。. Example: <code>+-0pBNvYgxwmi/#iw</code></p>
        </div>
        </form>

                    <h2 id="-GETapi-v1-auth--provider--redirect">重定向至第三方登录 (OAuth)</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth--provider--redirect">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/architecto/redirect" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/architecto/redirect"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth--provider--redirect">
            <blockquote>
            <p>示例响应 (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;code&quot;: 500,
    &quot;message&quot;: &quot;Driver [architecto] not supported.&quot;,
    &quot;exception&quot;: &quot;InvalidArgumentException&quot;,
    &quot;timestamp&quot;: &quot;2026-03-23T16:27:51+08:00&quot;,
    &quot;debug&quot;: {
        &quot;database&quot;: {
            &quot;total&quot;: 0,
            &quot;items&quot;: []
        },
        &quot;cache&quot;: {
            &quot;hit&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;miss&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;write&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;forget&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            }
        },
        &quot;profiling&quot;: [
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3439910411834717
            }
        ],
        &quot;memory&quot;: {
            &quot;usage&quot;: 12589896,
            &quot;peak&quot;: 13215944
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth--provider--redirect" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-GETapi-v1-auth--provider--redirect"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth--provider--redirect"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth--provider--redirect" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth--provider--redirect">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-GETapi-v1-auth--provider--redirect" data-method="GET"
      data-path="api/v1/auth/{provider}/redirect"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth--provider--redirect', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth--provider--redirect"
                    onclick="tryItOut('GETapi-v1-auth--provider--redirect');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth--provider--redirect"
                    onclick="cancelTryOut('GETapi-v1-auth--provider--redirect');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth--provider--redirect"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/{provider}/redirect</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth--provider--redirect"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth--provider--redirect"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL 参数</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="GETapi-v1-auth--provider--redirect"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="-GETapi-v1-auth--provider--callback">处理第三方登录回调</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth--provider--callback">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/architecto/callback" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/architecto/callback"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth--provider--callback">
            <blockquote>
            <p>示例响应 (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;code&quot;: 500,
    &quot;message&quot;: &quot;Driver [architecto] not supported.&quot;,
    &quot;exception&quot;: &quot;InvalidArgumentException&quot;,
    &quot;timestamp&quot;: &quot;2026-03-23T16:27:51+08:00&quot;,
    &quot;debug&quot;: {
        &quot;database&quot;: {
            &quot;total&quot;: 0,
            &quot;items&quot;: []
        },
        &quot;cache&quot;: {
            &quot;hit&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;miss&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;write&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;forget&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            }
        },
        &quot;profiling&quot;: [
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3439910411834717
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.34911489486694336
            }
        ],
        &quot;memory&quot;: {
            &quot;usage&quot;: 12673584,
            &quot;peak&quot;: 13215944
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth--provider--callback" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-GETapi-v1-auth--provider--callback"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth--provider--callback"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth--provider--callback" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth--provider--callback">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-GETapi-v1-auth--provider--callback" data-method="GET"
      data-path="api/v1/auth/{provider}/callback"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth--provider--callback', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth--provider--callback"
                    onclick="tryItOut('GETapi-v1-auth--provider--callback');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth--provider--callback"
                    onclick="cancelTryOut('GETapi-v1-auth--provider--callback');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth--provider--callback"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/{provider}/callback</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth--provider--callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth--provider--callback"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL 参数</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>provider</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="provider"                data-endpoint="GETapi-v1-auth--provider--callback"
               value="architecto"
               data-component="url">
    <br>
<p>Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="-POSTapi-v1-auth-password-request">请求重置密码邮件</h2>

<p>
</p>



<span id="example-requests-POSTapi-v1-auth-password-request">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/password/request" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"email\": \"gbailey@example.net\",
    \"old_password\": \"architecto\",
    \"new_password\": \"ngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwt\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/password/request"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "email": "gbailey@example.net",
    "old_password": "architecto",
    "new_password": "ngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwt"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-password-request">
</span>
<span id="execution-results-POSTapi-v1-auth-password-request" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-password-request"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-password-request"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-password-request" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-password-request">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-password-request" data-method="POST"
      data-path="api/v1/auth/password/request"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-password-request', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-password-request"
                    onclick="tryItOut('POSTapi-v1-auth-password-request');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-password-request"
                    onclick="cancelTryOut('POSTapi-v1-auth-password-request');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-password-request"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/password/request</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-password-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-password-request"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>正文参数</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>email</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="email"                data-endpoint="POSTapi-v1-auth-password-request"
               value="gbailey@example.net"
               data-component="body">
    <br>
<p>value 不是一个合法的邮箱。 The <code>email</code> of an existing record in the users table. Example: <code>gbailey@example.net</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>old_password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="old_password"                data-endpoint="POSTapi-v1-auth-password-request"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>new_password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="new_password"                data-endpoint="POSTapi-v1-auth-password-request"
               value="ngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwt"
               data-component="body">
    <br>
<p>value 至少为 6 个字符。. Example: <code>ngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzsnrwt</code></p>
        </div>
        </form>

                    <h2 id="-GETapi-v1-auth-password-confirm">确认并执行密码重置</h2>

<p>
</p>



<span id="example-requests-GETapi-v1-auth-password-confirm">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/password/confirm" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"user\": 16,
    \"hash\": \"architecto\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/password/confirm"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "user": 16,
    "hash": "architecto"
};

fetch(url, {
    method: "GET",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-password-confirm">
            <blockquote>
            <p>示例响应 (422):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;code&quot;: 422,
    &quot;message&quot;: &quot;Unprocessable Content&quot;,
    &quot;errors&quot;: {
        &quot;user&quot;: [
            &quot;user 不存在。&quot;
        ]
    },
    &quot;exception&quot;: &quot;ValidationException&quot;,
    &quot;timestamp&quot;: &quot;2026-03-23T16:27:51+08:00&quot;,
    &quot;debug&quot;: {
        &quot;database&quot;: {
            &quot;total&quot;: 1,
            &quot;items&quot;: [
                {
                    &quot;connection&quot;: &quot;pgsql&quot;,
                    &quot;query&quot;: &quot;select count(*) as aggregate from \&quot;users\&quot; where \&quot;id\&quot; = &#039;16&#039;;&quot;,
                    &quot;time&quot;: 10.81
                }
            ]
        },
        &quot;cache&quot;: {
            &quot;hit&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;miss&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;write&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;forget&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            }
        },
        &quot;profiling&quot;: [
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3439910411834717
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.34911489486694336
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3710620403289795
            }
        ],
        &quot;memory&quot;: {
            &quot;usage&quot;: 12940944,
            &quot;peak&quot;: 14018512
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-password-confirm" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-GETapi-v1-auth-password-confirm"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-password-confirm"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-password-confirm" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-password-confirm">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-GETapi-v1-auth-password-confirm" data-method="GET"
      data-path="api/v1/auth/password/confirm"
      data-authed="0"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-password-confirm', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-password-confirm"
                    onclick="tryItOut('GETapi-v1-auth-password-confirm');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-password-confirm"
                    onclick="cancelTryOut('GETapi-v1-auth-password-confirm');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-password-confirm"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/password/confirm</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-password-confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-password-confirm"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>正文参数</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="user"                data-endpoint="GETapi-v1-auth-password-confirm"
               value="16"
               data-component="body">
    <br>
<p>The <code>id</code> of an existing record in the users table. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>hash</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="hash"                data-endpoint="GETapi-v1-auth-password-confirm"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

                    <h2 id="-GETapi-v1-auth-me">获取当前认证用户信息</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-me">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/me" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/me"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-me">
            <blockquote>
            <p>示例响应 (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;success&quot;: false,
    &quot;code&quot;: 401,
    &quot;message&quot;: &quot;Unauthorized&quot;,
    &quot;exception&quot;: &quot;AuthenticationException&quot;,
    &quot;timestamp&quot;: &quot;2026-03-23T16:27:51+08:00&quot;,
    &quot;debug&quot;: {
        &quot;database&quot;: {
            &quot;total&quot;: 1,
            &quot;items&quot;: [
                {
                    &quot;connection&quot;: &quot;pgsql&quot;,
                    &quot;query&quot;: &quot;select count(*) as aggregate from \&quot;users\&quot; where \&quot;id\&quot; = &#039;16&#039;;&quot;,
                    &quot;time&quot;: 10.81
                }
            ]
        },
        &quot;cache&quot;: {
            &quot;hit&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;miss&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;write&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            },
            &quot;forget&quot;: {
                &quot;keys&quot;: [],
                &quot;total&quot;: 0
            }
        },
        &quot;profiling&quot;: [
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3439910411834717
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.34911489486694336
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3710620403289795
            },
            {
                &quot;event&quot;: &quot;request-time&quot;,
                &quot;time&quot;: 0.3850879669189453
            }
        ],
        &quot;memory&quot;: {
            &quot;usage&quot;: 13079168,
            &quot;peak&quot;: 14018512
        }
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-me" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-GETapi-v1-auth-me"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-me"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-me" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-me">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-GETapi-v1-auth-me" data-method="GET"
      data-path="api/v1/auth/me"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-me', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-me"
                    onclick="tryItOut('GETapi-v1-auth-me');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-me"
                    onclick="cancelTryOut('GETapi-v1-auth-me');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-me"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/me</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-me"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="-POSTapi-v1-auth-logout">退出登录</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-logout">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/logout" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/logout"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-logout">
</span>
<span id="execution-results-POSTapi-v1-auth-logout" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-logout"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-logout"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-logout" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-logout">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-logout" data-method="POST"
      data-path="api/v1/auth/logout"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-logout', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-logout"
                    onclick="tryItOut('POSTapi-v1-auth-logout');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-logout"
                    onclick="cancelTryOut('POSTapi-v1-auth-logout');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-logout"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/logout</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-logout"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="-POSTapi-v1-auth-refresh">刷新访问令牌 (Token)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-refresh">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/refresh" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/refresh"
);

const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-refresh">
</span>
<span id="execution-results-POSTapi-v1-auth-refresh" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-refresh"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-refresh"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-refresh" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-refresh">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-refresh" data-method="POST"
      data-path="api/v1/auth/refresh"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-refresh', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-refresh"
                    onclick="tryItOut('POSTapi-v1-auth-refresh');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-refresh"
                    onclick="cancelTryOut('POSTapi-v1-auth-refresh');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-refresh"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/refresh</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-refresh"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-refresh"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="-POSTapi-v1-auth-profile">更新用户个人资料</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-profile">
<blockquote>示例请求:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/profile" \
    --header "Content-Type: multipart/form-data" \
    --header "Accept: application/json" \
    --form "telephone=bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzs"\
    --form "nickname=n"\
    --form "gender=architecto"\
    --form "avatar=@/private/var/folders/3j/nnzw6fw54_n_xzgy8tgxgg5c0000gn/T/phpjmbf8m75rs3bbNJOzPC" </code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/profile"
);

const headers = {
    "Content-Type": "multipart/form-data",
    "Accept": "application/json",
};

const body = new FormData();
body.append('telephone', 'bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzs');
body.append('nickname', 'n');
body.append('gender', 'architecto');
body.append('avatar', document.querySelector('input[name="avatar"]').files[0]);

fetch(url, {
    method: "POST",
    headers,
    body,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-profile">
</span>
<span id="execution-results-POSTapi-v1-auth-profile" hidden>
    <blockquote>已收到响应<span
                id="execution-response-status-POSTapi-v1-auth-profile"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-profile"
      data-empty-response-text="<空响应>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-profile" hidden>
    <blockquote>请求失败，错误为:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-profile">

提示：请检查您的网络连接是否正常。
如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
您可以查看开发人员工具控制台以获取调试信息。</code></pre>
</span>
<form id="form-POSTapi-v1-auth-profile" data-method="POST"
      data-path="api/v1/auth/profile"
      data-authed="1"
      data-hasfiles="1"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-profile', this);">
    <h3>
        请求&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-profile"
                    onclick="tryItOut('POSTapi-v1-auth-profile');">立即试用 ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-profile"
                    onclick="cancelTryOut('POSTapi-v1-auth-profile');" hidden>取消 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-profile"
                    data-initial-text="发送请求 💥"
                    data-loading-text="⏱ 正在发送..."
                    hidden>发送请求 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/profile</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>标头</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-profile"
               value="multipart/form-data"
               data-component="header">
    <br>
<p>Example: <code>multipart/form-data</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-profile"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>正文参数</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>avatar</code></b>&nbsp;&nbsp;
<small>file</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="file" style="display: none"
                              name="avatar"                data-endpoint="POSTapi-v1-auth-profile"
               value=""
               data-component="body">
    <br>
<p>value 必须是图片。 value 不能大于 2048 KB。. Example: <code>/private/var/folders/3j/nnzw6fw54_n_xzgy8tgxgg5c0000gn/T/phpjmbf8m75rs3bbNJOzPC</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>telephone</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="telephone"                data-endpoint="POSTapi-v1-auth-profile"
               value="bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzs"
               data-component="body">
    <br>
<p>Must match the regex /^1[3-9]\d{9}$/. value 至少为 10 个字符。. Example: <code>bngzmiyvdljnikhwaykcmyuwpwlvqwrsitcpscqldzs</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>nickname</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="nickname"                data-endpoint="POSTapi-v1-auth-profile"
               value="n"
               data-component="body">
    <br>
<p>value 不能大于 255 个字符。. Example: <code>n</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>gender</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="gender"                data-endpoint="POSTapi-v1-auth-profile"
               value="architecto"
               data-component="body">
    <br>
<p>Example: <code>architecto</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
