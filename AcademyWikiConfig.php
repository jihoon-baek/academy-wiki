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
        document.documentElement.classList.add('skin-theme-clientpref-' + theme);
        try { 
            localStorage.setItem('skin-theme-prefs', theme); 
            localStorage.setItem('mw-theme', theme); 
        } catch(e) {}
    }
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

/* Typography */
body {
    font-family: Pretendard, -apple-system, BlinkMacSystemFont, "Apple SD Gothic Neo", "Noto Sans KR", "Malgun Gothic", sans-serif !important;
}

/* Light Mode Variables - Namuwiki Style */
:root, html.skin-theme-clientpref-day {
    --background-color-base: #ffffff;
    --background-color-page: #f5f6f7;
    --color-base: #1f2023;
    --color-link: #0275d8;
    --border-color-base: #cccccc;
    
    --namu-header-bg: #00A495;
    --namu-header-color: #ffffff;
    --namu-header-border: #008275;
}

/* Dark Mode Variables - Namuwiki/Obsidian Style */
html.skin-theme-clientpref-night {
    --background-color-base: #1c1d1f;
    --background-color-page: #101010;
    --color-base: #e0e0e0;
    --color-link: #A882FF; /* Obsidian Purple for dark mode links */
    --border-color-base: #333333;
    
    --namu-header-bg: #1c1d1f;
    --namu-header-color: #ffffff;
    --namu-header-border: #2c2c2c;
}

/* Page Background */
body, html {
    background-color: var(--background-color-page) !important;
}

/* Header Styling (Namuwiki top bar) */
.vector-header-container {
    background-color: var(--namu-header-bg) !important;
    border-bottom: 1px solid var(--namu-header-border) !important;
}
.vector-header-container, 
.vector-header-container * {
    color: var(--namu-header-color) !important;
}

/* Search Box in Header */
.vector-search-box-input {
    background-color: rgba(255,255,255,0.1) !important;
    border: 1px solid rgba(255,255,255,0.3) !important;
    color: var(--namu-header-color) !important;
}
.vector-search-box-input::placeholder {
    color: rgba(255,255,255,0.7) !important;
}

/* Content Container (Card styling) */
.mw-page-container {
    background-color: var(--background-color-page) !important;
}
.mw-content-container {
    background-color: var(--background-color-base) !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color-base);
    border-radius: 10px;
    padding: 2.5em;
    margin-top: 15px;
    margin-bottom: 40px;
}

/* Link overrides */
a {
    color: var(--color-link);
}

/* Headings (Namuwiki style) */
.mw-parser-output h1,
.mw-parser-output h2,
.mw-parser-output h3,
.mw-parser-output h4 {
    border-bottom: 1px solid var(--border-color-base) !important;
    padding-bottom: 0.3em;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    font-weight: 700;
}

/* Table of Contents */
.toc, .vector-toc {
    background-color: var(--background-color-base) !important;
    border: 1px solid var(--border-color-base) !important;
    border-radius: 6px;
    padding: 15px;
}
CSS;


    $out->addInlineScript( $js );
    $out->addInlineStyle( $css );
};
