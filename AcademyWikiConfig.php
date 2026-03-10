<?php
// AcademyWikiConfig.php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\UserDataAuthenticationRequest;

class EmailDomainCheckProvider extends AbstractPreAuthenticationProvider {
	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$email = '';
		foreach ( $reqs as $req ) {
			if ( $req instanceof UserDataAuthenticationRequest ) {
				$email = $req->email;
			}
		}
		
		// @pos.idserve.net 도메인 검증
		if ( !preg_match( '/@pos\.idserve\.net$/i', $email ) ) {
			return \StatusValue::newFatal( wfMessage( 'rawmessage', '가입은 @pos.idserve.net 이메일 주소로만 가능합니다.' ) );
		}
		return \StatusValue::newGood();
	}
}

// ---------------------------------------------------------
// 1. 위키 읽기/쓰기 권한 제어 (비공개 위키)
// ---------------------------------------------------------
$wgGroupPermissions['*']['read'] = false;
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['createaccount'] = true; // 가입은 허용하되, 이메일로 필터링

// 비로그인 사용자에게 공개할 페이지 화이트리스트 (대문, 로그인/가입 관련 필수 페이지)
$wgWhitelistRead = [
	'대문',
	'Main Page',
	'특수:CreateAccount',
	'Special:CreateAccount',
	'특수:UserLogin',
	'Special:UserLogin',
	'특수:PasswordReset',
	'Special:PasswordReset',
	'특수:초기화',
	'특수:비밀번호재설정'
];

// 이메일 기반 가입 제한 프로바이더 등록
$wgAuthManagerAutoConfig['preauth']['EmailDomainCheck'] = [
	'class' => EmailDomainCheckProvider::class,
	'sort' => 10,
];

// 이메일 주소를 가입 시 필수 항목으로 지정하기 위한 추가 설정 (1.43에서는 auth manager가 제어)
// UI에서 이메일을 무조건 입력하도록 유도해야 함. (기본적으로 이메일은 선택사항)
$wgHooks['AuthChangeFormFields'][] = function ( $requests, $fieldInfo, &$formDescriptor, $action ) {
	if ( $action === \MediaWiki\Auth\AuthManager::ACTION_CREATE && isset( $formDescriptor['email'] ) ) {
		$formDescriptor['email']['optional'] = false; // 이메일 생략 불가
	}
};

// --- 옵시디언/마크다운 문법 지원 (PHP 정규식 훅) ---
$wgHooks['ParserBeforeInternalParse'][] = function( &$parser, &$text, &$stripState ) {
    // 1. 헤드라인 (# ~ ######)
    $text = preg_replace('/^###### (.*?)$/m', '====== $1 ======', $text);
    $text = preg_replace('/^##### (.*?)$/m', '===== $1 =====', $text);
    $text = preg_replace('/^#### (.*?)$/m', '==== $1 ====', $text);
    $text = preg_replace('/^### (.*?)$/m', '=== $1 ===', $text);
    $text = preg_replace('/^## (.*?)$/m', '== $1 ==', $text);
    $text = preg_replace('/^# (.*?)$/m', '= $1 =', $text);

    // 2. 리스트 (- 항목)
    $text = preg_replace('/^(\s*)- (.*?)$/m', '$1* $2', $text);

    // 3. 인용문 (> 항목)
    $text = preg_replace('/^> (.*?)$/m', '<blockquote>$1</blockquote>', $text);

    // 4. 일반 마크다운 링크 [텍스트](URL) -> 미디어위키 [URL 텍스트]
    $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/', '[$2 $1]', $text);

    return true;
};

// ---------------------------------------------------------
// 2. Vector 2022 및 미디어위키 기본 설정
// ---------------------------------------------------------
$wgVectorEnableNightMode = true; // 네이티브 다크모드 기반 (선택)

// ---------------------------------------------------------
// 3. 다크모드/라이트모드 토글 버튼 (좌측 하단 커스텀 UI)
// ---------------------------------------------------------
$wgHooks['BeforePageDisplay'][] = function ( OutputPage $out, Skin $skin ) {
    $js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('custom-theme-toggle')) return;

    var toggleHtml = '<div id="custom-theme-toggle" class="theme-toggle" title="다크/라이트 모드 설정">' +
        '<input type="checkbox" id="theme-toggle-checkbox">' +
        '<label for="theme-toggle-checkbox" class="theme-toggle-label">' +
        '<span class="toggle-icon moon-icon">🌙</span>' +
        '<span class="toggle-icon sun-icon">☀️</span>' +
        '<span class="toggle-ball"></span>' +
        '</label></div>';

    document.body.insertAdjacentHTML('beforeend', toggleHtml);

    var checkbox = document.getElementById('theme-toggle-checkbox');
    var isNight = document.documentElement.className.indexOf('skin-theme-clientpref-night') !== -1;
    if (!isNight && localStorage.getItem('mw-theme') === 'night') {
        isNight = true;
    }

    if (isNight) {
        checkbox.checked = true;
        applyTheme('night');
    }

    checkbox.addEventListener('change', function() {
        if (this.checked) {
            applyTheme('night');
        } else {
            applyTheme('day');
        }
    });

    function applyTheme(theme) {
        document.documentElement.classList.remove('skin-theme-clientpref-os', 'skin-theme-clientpref-day', 'skin-theme-clientpref-night');
    }

    // Forcefully unpin the main menu for existing stuck sessions
    setTimeout(function() {
        var unpinBtn = document.querySelector('.vector-pinnable-header-unpin-button[data-event-name="pinnable-header.vector-main-menu.unpin"]');
        if (unpinBtn) {
            unpinBtn.click();
        }
        // Also clear native cookies/options
        mw.loader.using('mediawiki.cookie').then(function() {
            mw.cookie.set('vector-main-menu-pinned', '0');
        });

        // --- 게시글 우측 도구들을 하단으로 이동 ---
        var pageTools = document.querySelector('.vector-page-tools-landmark');
        var catLinks = document.getElementById('catlinks');
        var contentBottom = document.querySelector('.mw-content-container');
        if (pageTools && contentBottom) {
            // 이미 하단에 있는게 아니면 하단으로 이동
            contentBottom.appendChild(pageTools);
            pageTools.style.marginTop = '40px';
            pageTools.style.borderTop = '1px solid var(--border-color)';
            pageTools.style.paddingTop = '20px';
            pageTools.style.display = 'block'; // 강제로 보이게 함
        }
    }, 150);
});
JS;

    $css = <<<CSS
#custom-theme-toggle {
    position: fixed;
    bottom: 25px;
    left: 25px;
    z-index: 9999;
}
.theme-toggle input {
    display: none;
}
.theme-toggle-label {
    background-color: #333;
    border-radius: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px;
    position: relative;
    height: 32px;
    width: 64px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    box-sizing: border-box;
}
.theme-toggle-label .toggle-icon {
    font-size: 14px;
    line-height: 1;
    z-index: 10;    
}
.moon-icon {
    margin-left: 2px;
    filter: grayscale(1) brightness(1.5);
}
.sun-icon {
    margin-right: 2px;
    filter: grayscale(1) brightness(0.5);
}
.theme-toggle-label .toggle-ball {
    background-color: #fff;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    left: 3px;
    height: 26px;
    width: 26px;
    transform: translateX(0px);
    transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    z-index: 1;
}
.theme-toggle input:checked + .theme-toggle-label {
    background-color: #e5e5e5;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.theme-toggle input:checked + .theme-toggle-label .toggle-ball {
    transform: translateX(32px);
    background-color: #333;
}
.theme-toggle input:checked + .theme-toggle-label .sun-icon {
    filter: none;
}
.theme-toggle input:checked + .theme-toggle-label .moon-icon {
    filter: grayscale(1) brightness(0.5);
}

/* Fallback for native dark mode classes on Vector skin */
html.skin-theme-clientpref-night body {
    background-color: #121212 !important;
}

/* === Namuwiki / Obsidian Inspired Custom Theme === */

/* Typography & Base Variables */
body {
    font-family: Pretendard, -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Noto Sans KR", "Malgun Gothic", sans-serif !important;
    word-break: keep-all;
    font-size: 15px;
    letter-spacing: -0.2px;
}

/* Light Mode Variables - Namuwiki Style */
:root, html.skin-theme-clientpref-day {
    --bg-base: #ffffff;
    --bg-page: #f5f6f7;
    --text-main: #1f2023;
    --text-muted: #666666;
    --link-color: #0275d8;
    --link-hover: #014c8c;
    --border-color: #e5e5e5;
    
    --header-bg: #ffffff; /* White Header */
    --header-text: #1f2023; /* Dark text */
    
    --card-bg: #ffffff;
    --card-shadow: 0 2px 8px rgba(0,0,0,0.08);
    
    --toc-bg: #f8f9fa;
    --toc-border: #e2e2e2;
    --heading-border: #cccccc;
    
    --table-header-bg: #f0f0f0;
    --table-border: #dee2e6;
}

/* Dark Mode Variables - Namuwiki/Obsidian Style */
html.skin-theme-clientpref-night {
    --bg-base: #1c1d1f;
    --bg-page: #151515;
    --text-main: #e0e0e0;
    --text-muted: #999999;
    --link-color: #A882FF; /* Obsidian Purple */
    --link-hover: #c4a9ff;
    --border-color: #383838;
    
    --header-bg: #1c1d1f; /* Dark header */
    --header-text: #e0e0e0;
    
    --card-bg: #1c1d1f;
    --card-shadow: 0 4px 12px rgba(0,0,0,0.5);
    
    --toc-bg: #222222;
    --toc-border: #444444;
    --heading-border: #444444;
    
    --table-header-bg: #2a2a2a;
    --table-border: #444444;
}

/* Global Background */
body, html, .mw-page-container {
    background-color: var(--bg-page) !important;
    color: var(--text-main) !important;
}

/* --- 1. Top Navigation Bar (Namuwiki Header) --- */
.vector-header-container {
    background-color: var(--header-bg) !important;
    border-bottom: 1px solid var(--border-color) !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.vector-header-container * {
    color: var(--header-text) !important;
}
/* Search Box styling */
.vector-search-box-input {
    background-color: transparent !important;
    border: 1px solid var(--heading-border) !important;
    color: var(--text-main) !important;
    border-radius: 4px !important;
    padding-left: 10px !important;
}
.vector-search-box-input::placeholder {
    color: var(--text-muted) !important;
}
.vector-search-box-input:focus {
    background-color: var(--card-bg) !important;
    color: var(--text-main) !important;
}
/* User Links (Login/Create Account buttons) */
.vector-user-menu-login, .vector-user-menu-create-account {
    border-radius: 4px !important;
    padding: 2px 10px !important;
    background: rgba(0,0,0,0.05) !important;
}
html.skin-theme-clientpref-night .vector-user-menu-login, html.skin-theme-clientpref-night .vector-user-menu-create-account {
    background: rgba(255,255,255,0.1) !important;
}
.vector-menu-tabs .mw-list-item.selected a, .vector-menu-tabs .mw-list-item.selected a:visited {
    color: var(--text-main) !important;
    border-bottom: 3px solid var(--text-main) !important;
}

/* --- 2. Content Container (Card Layout) --- */
.mw-content-container {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color);
    box-shadow: var(--card-shadow) !important;
    border-radius: 8px;
    padding: 30px !important;
    margin-top: 20px !important;
    margin-bottom: 50px !important;
    max-width: 1000px !important; /* Force a cleaner width like Namuwiki */
    margin-left: auto !important;
    margin-right: auto !important;
}

/* --- 3. Sidebar (Left / Right panels) --- */
.vector-column-start, .vector-column-end {
    background: transparent !important;
}
/* Hide default annoying borders */
.mw-page-container-inner {
    grid-template-columns: minmax(0, 1fr) !important; /* Hide left sidebar by default on some pages if needed, but Vector 2022 toggles it anyway */
}

/* --- 4. Typography & Links --- */
a {
    color: var(--link-color) !important;
    text-decoration: none !important;
    transition: color 0.1s;
}
a:hover {
    color: var(--link-hover) !important;
    text-decoration: underline !important;
}
/* External links remove the weird arrow icon */
.mw-parser-output a.external {
    background-image: none !important;
    padding-right: 0 !important;
}
/* Red links (Not yet created pages) */
a.new, #p-personal a.new {
    color: #cc0000 !important;
}
html.skin-theme-clientpref-night a.new {
    color: #ff6b6b !important; /* Brighter red for dark mode */
}

/* --- 5. Headings (Namuwiki Style) --- */
.mw-parser-output h1, .mw-parser-output h2, .mw-parser-output h3, .mw-parser-output h4, .mw-parser-output h5, .mw-parser-output h6 {
    color: var(--text-main) !important;
    border-bottom: 1px solid var(--heading-border) !important;
    padding-bottom: 8px !important;
    margin-top: 1.8em !important;
    margin-bottom: 12px !important;
    font-weight: 700 !important;
    line-height: 1.3 !important;
}
.skin-vector-2022 .mw-page-title-main {
    font-size: 2em !important;
    font-weight: 800 !important;
    border-bottom: 2px solid var(--heading-border) !important;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
/* Add section numbers simulation (optional, Vector already has them if enabled in prefs, but let's make them look like Namuwiki) */
.mw-headline-number {
    color: var(--header-bg) !important;
    margin-right: 6px;
}

/* --- 6. Table of Contents (TOC) --- */
.toc, .mw-ext-toc, .vector-toc {
    background-color: var(--toc-bg) !important;
    border: 1px solid var(--toc-border) !important;
    border-radius: 6px !important;
    padding: 20px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05) !important;
    display: inline-block !important; /* Make it a neat box */
    min-width: 300px;
    margin-top: 1em;
    margin-bottom: 2em;
}
.vector-toc-title {
    font-weight: bold !important;
    font-size: 1.1em !important;
    color: var(--text-main) !important;
    border-bottom: 1px solid var(--heading-border) !important;
    padding-bottom: 10px !important;
    margin-bottom: 10px !important;
}
.vector-toc .vector-toc-list-item a {
    color: var(--text-main) !important;
    font-size: 0.95em !important;
    padding: 4px 0 !important;
}
.vector-toc .vector-toc-list-item a:hover {
    color: var(--link-color) !important;
    text-decoration: underline !important;
}
.vector-toc-level-1 { margin-left: 0 !important; }
.vector-toc-level-2 { margin-left: 15px !important; }

/* --- 7. Tables & Infoboxes (Namuwiki Info table clone) --- */
.mw-parser-output table.wikitable, .mw-parser-output table.infobox {
    background-color: var(--card-bg) !important;
    color: var(--text-main) !important;
    border: 2px solid var(--table-border) !important;
    border-collapse: collapse !important;
    border-radius: 4px !important;
    overflow: hidden !important; /* For rounded corners on tables */
}
.mw-parser-output table.wikitable > tr > th, .mw-parser-output table.wikitable > tbody > tr > th,
.mw-parser-output table.infobox th {
    background-color: var(--table-header-bg) !important;
    border: 1px solid var(--table-border) !important;
    font-weight: bold !important;
    color: var(--text-main) !important;
    padding: 10px !important;
    text-align: center !important;
}
.mw-parser-output table.wikitable > tr > td, .mw-parser-output table.wikitable > tbody > tr > td,
.mw-parser-output table.infobox td {
    border: 1px solid var(--table-border) !important;
    padding: 10px !important;
}
/* Infobox specific tweaks (the right-side floating table like actor profiles) */
.mw-parser-output table.infobox {
    float: right !important;
    clear: right !important;
    margin: 0 0 1em 1.5em !important;
    width: 300px !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* --- 8. Blockquotes & Info blocks --- */
.mw-parser-output blockquote {
    background-color: rgba(0, 164, 149, 0.05); /* Very light Namu green */
    border-left: 4px solid var(--header-bg) !important;
    margin: 1.5em 0 !important;
    padding: 15px 20px !important;
    border-radius: 0 4px 4px 0 !important;
}
html.skin-theme-clientpref-night .mw-parser-output blockquote {
    background-color: rgba(255, 255, 255, 0.05); /* Mild white for dark mode */
}

/* --- 9. Categories at the bottom --- */
.catlinks {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 6px !important;
    padding: 15px !important;
    margin-top: 30px !important;
}

/* Miscellaneous Vector Fixes */
.vector-page-toolbar { margin-top: 10px !important; }
.vector-body { font-size: 1em !important; line-height: 1.6 !important; }
.mw-editsection {
    font-size: 0.6em !important;
    margin-left: 10px !important;
    opacity: 0.5;
    transition: opacity 0.2s;
}
.mw-editsection:hover {
    opacity: 1;
}
.mw-editsection a {
    color: var(--text-muted) !important;
}

/* --- 10. Logo Adjustments --- */
/* Hide the square icon and show only the full "wordmark" logo */
.mw-logo-icon {
    display: none !important;
}
.mw-logo-container {
    margin: 0 !important;
    padding: 0 !important;
}
.mw-logo-wordmark {
    height: 30px !important;
    max-height: 30px !important;
    width: auto !important;
}

/* --- 11. Main Menu Pinning Disable --- */
/* Hide the "Move to sidebar" (pin/unpin) button for the main menu */
.vector-pinnable-header-unpin-button[data-event-name="pinnable-header.vector-main-menu.unpin"] {
    visibility: hidden !important;
    position: absolute !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

/* --- 12. Appearance & Sidebar Tools Refactor --- */
/* Hide the entire 'Appearance' (보이기/숨기기) menu */
.vector-appearance-landmark, 
#vector-appearance-dropdown,
.vector-dropdown[title*="모습을 변경합니다"],
#vector-appearance-unpinned-container,
#p-appearance {
    display: none !important;
}

/* Hide the top right Page Tools dropdown */
#vector-page-tools-dropdown, .vector-page-tools-landmark .vector-dropdown {
    display: none !important;
}

/* Hide the Right Sidebar column entirely */
.vector-column-end {
    display: none !important;
}

/* Bottom Page Tools Styling */
.vector-page-tools-landmark {
    display: block !important;
    width: 100% !important;
    background: var(--bg-page) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    padding: 20px !important;
    box-sizing: border-box !important;
    margin-top: 40px !important;
}
.vector-page-tools-landmark .vector-pinned-container {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 30px !important;
}
.vector-page-tools-landmark .vector-menu {
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
}
.vector-page-tools-landmark .vector-menu-heading {
    font-weight: 800 !important;
    color: var(--text-main) !important;
    margin-bottom: 10px !important;
    display: block !important;
}
.vector-page-tools-landmark .vector-menu-content-list {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 15px !important;
    list-style: none !important;
    padding: 0 !important;
}
.vector-page-tools-landmark .vector-menu-content-list li {
    font-size: 0.9em !important;
}
CSS;

    $out->addInlineScript( $js );
    $out->addInlineStyle( $css );
};
