<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<title>{title}</title>
	<meta name="description" content="{description}">
	<meta name="viewport" content="width=device-width">
	<link rel="stylesheet" type="text/css" href="/css/app.css?{css_change_time}">
	<link rel="stylesheet" type="text/css" href="/css/prism.css">
{head_addon}
	<script src="https://use.fontawesome.com/bbd622fe65.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/sjcl/1.0.7/sjcl.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.8.4/prism.min.js"></script>
	<script type="text/javascript" src="https://unpkg.com/golos-js@0.7.0/dist/golos.min.js"></script>
	<script type="text/javascript" src="/js/app2.js?{script_change_time}"></script>
	<link rel="icon" type="image/x-icon" href="/favicon.ico">
	<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
	<link rel="apple-touch-icon" sizes="57x57" href="/favicon/apple-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="/favicon/apple-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="/favicon/apple-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="/favicon/apple-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="/favicon/apple-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="/favicon/apple-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="/favicon/apple-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/favicon/apple-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-icon-180x180.png">
	<link rel="icon" type="image/png" sizes="192x192" href="/favicon/android-icon-192x192.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="96x96" href="/favicon/favicon-96x96.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
	<link rel="manifest" href="/favicon/manifest.json">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="/favicon/ms-icon-144x144.png">
	<meta name="theme-color" content="#4f4d98">
</head>
<body class="global-wrap">
<div class="section header-line">
<div class="content-wrapper-inside-left-revert"></div>
<div class="content-wrapper-inside-right-revert"></div>
	<div class="content-wrapper">
		<a class="adaptive-menu"><i class="fa fa-fw fa-bars" aria-hidden="true"></i></a>
		<a href="/" class="header-logo"><img src="/logo_white.svg" alt=""></a>
		<div class="search-box"><input type="text" id="search-textbox" value="{l10n_global_search}" onfocus="if('{l10n_global_search}'==this.value){this.value='';}" onblur="if(''==this.value){this.value='{l10n_global_search}';}"></div>
		<div id="user-block" class="header-right">
			{user_block}
		</div>
	</div>
</div>
<div class="main">
<div class="go-top-left-wrapper"><div class="go-top-button"><i class="fa fa-fw fa-chevron-up" aria-hidden="true"></i></div></div>
	<div class="content-wrapper">
<!-- + Page Layout -->
{page-before}
{page}
<!-- - Page Layout -->
	</div>
</div>
<footer>
	<div class="content-wrapper">
		<div class="footer-right">
			{l10n_footer_descr}
		</div>
		<div class="copyright">© <a href="https://goldvoice.club/">GoldVoice.club</a></div>
	</div>
</footer>
<div class="modal-overlay"></div>
<div class="modal-box" id="modal-drop-images" style="width:440px;"><i class="fa fa-fw fa-file-image-o" aria-hidden="true"></i> {l10n_modals_drop_image}</div>
<div class="modal-box" id="modal-login" style="width:440px;"><form action="/" method="POST" class="login-form">
	<h2>{l10n_modals_login_title}</h2>
	<div class="login_error">{l10n_errors_login}</div>
	<input type="text" name="login" placeholder="{l10n_modals_input_login}">
	<input type="password" name="posting_key" placeholder="{l10n_modals_form_posting_key}">
	<input type="button" name="login-button" value="{l10n_modals_form_login}">
	<input type="button" name="close-button" value="{l10n_modals_close_form}">
	<p>{l10n_modals_attention}</p></form>
</div>
<div class="view-dropdown-notifications">&hellip;</div>
<div class="view-dropdown-currencies">&hellip;</div>
<div class="view-dropdown"><a class="menu-my-page button button-line">{l10n_menu_my_page}</a><a class="button button-line" href="/settings/">{l10n_menu_settings}</a><a class="menu-logout button button-line">{l10n_menu_logout}</a></div>
<div class="notify-list"></div>
</body>
</html>