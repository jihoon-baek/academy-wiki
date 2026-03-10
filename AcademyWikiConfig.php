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
CSS;

    $out->addInlineScript( $js );
    $out->addInlineStyle( $css );
};
