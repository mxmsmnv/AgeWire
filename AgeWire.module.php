<?php namespace ProcessWire;

class AgeWire extends WireData implements Module, ConfigurableModule {

    // ── Supported CSS frameworks ────────────────────────────────────────────
    const FRAMEWORK_VANILLA   = 'vanilla';
    const FRAMEWORK_TAILWIND  = 'tailwind';
    const FRAMEWORK_BOOTSTRAP = 'bootstrap';
    const FRAMEWORK_UIKIT     = 'uikit';

    // ── CDN URLs ────────────────────────────────────────────────────────────
    const CDN_TAILWIND        = 'https://cdn.tailwindcss.com';
    const CDN_BOOTSTRAP_CSS   = 'https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css';
    const CDN_BOOTSTRAP_JS    = 'https://cdn.jsdelivr.net/npm/bootstrap@5/dist/js/bootstrap.bundle.min.js';
    const CDN_UIKIT_CSS       = 'https://cdn.jsdelivr.net/npm/uikit@3/dist/css/uikit.min.css';
    const CDN_UIKIT_JS        = 'https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit.min.js';
    const CDN_UIKIT_ICONS     = 'https://cdn.jsdelivr.net/npm/uikit@3/dist/js/uikit-icons.min.js';

    public static function getModuleInfo(): array {
        return [
            'title'    => 'AgeWire',
            'summary'  => 'Age verification module with multi-framework support (Vanilla, Tailwind, Bootstrap, UIkit)',
            'version'  => '1.2.0',
            'author'   => 'Maxim Alex',
            'href'     => '',
            'singular' => true,
            'autoload' => true,
            'icon'     => 'calendar-check-o',
            'requires' => 'PHP>=8.2',
        ];
    }

    protected static array $defaultConfig = [
        // General
        'enabled'             => 1,
        'minimum_age'         => 18,
        'cookie_name'         => 'age_verified',
        'cookie_lifetime'     => 2592000,
        // Content
        'modal_title'         => 'Please verify your age',
        'modal_text'          => 'You must be {age} years or older to access this website.',
        'confirm_button_text' => 'I am {age} or older',
        'deny_button_text'    => 'I am under {age}',
        'redirect_url'        => 'https://www.responsibility.org/',
        // Exclusions
        'excluded_templates'  => [],
        'excluded_pages'      => [],
        // Date picker
        'show_date_picker'    => 0,
        'date_format'         => 'mdy',
        'date_picker_text'    => 'Please enter your date of birth:',
        'invalid_date_text'   => 'Please enter a valid date of birth.',
        'underage_text'       => 'Sorry, you must be {age} years or older to access this website.',
        // Agreement
        'show_agreement'      => 1,
        'agreement_text'      => 'By submitting this form, you agree to be bound by the Terms of Use and Privacy Policy',
        'privacy_policy_url'  => '/privacy-policy/',
        'terms_of_use_url'    => '/terms-of-use/',
        // Framework
        'css_framework'       => self::FRAMEWORK_TAILWIND,
        'load_cdn'            => 1,
        // Tailwind-specific
        'theme_style'         => 'modern',
        'animation_style'     => 'fade',
        // Custom
        'custom_css'          => '',
    ];

    // ════════════════════════════════════════════════════════════════════════
    // HOOKS
    // ════════════════════════════════════════════════════════════════════════

    public function init(): void {
        $this->addHookBefore('ProcessPageView::execute', $this, 'handleRequest');
    }

    public function handleRequest(HookEvent $event): void {
        $input = $this->wire('input');

        if ($input->post('age_verification_action') === 'verify_age') {
            $session        = $this->wire('session');
            $tokenName      = $session->CSRF->getTokenName();
            $tokenValue     = $session->CSRF->getTokenValue();
            $submittedToken = (string) $input->post($tokenName);

            if (!$submittedToken || !hash_equals($tokenValue, $submittedToken)) {
                $this->sendJson(['success' => false, 'message' => 'Invalid request.'], 403);
            }

            $this->processAgeVerification();
        }

        if ($this->enabled) {
            $this->addHookAfter('Page::render', $this, 'addAgeVerification');
        }
    }

    /**
     * Send a JSON response and terminate cleanly.
     *
     * ProcessWire hooks on ProcessPageView::execute cannot reliably prevent
     * the full page render via cancelAction alone — the page HTML would be
     * appended after our JSON in the output buffer, breaking JSON.parse on
     * the client. ob_clean() discards anything buffered so far, then exit
     * stops execution before PW renders the page. This matches the approach
     * used by ProcessWire's own Ajax handlers (FormBuilder, etc.).
     */
    protected function sendJson(array $data, int $status = 200): never {
        // Discard anything already buffered (partial page output, debug bars, etc.)
        if (ob_get_level()) ob_clean();

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function processAgeVerification(): void {
        $input    = $this->wire('input');
        $response = ['success' => false, 'message' => ''];

        try {
            if ($this->show_date_picker) {
                $birthDate = $input->post->text('birth_date');

                if (empty($birthDate)) {
                    $response['message'] = $this->invalid_date_text;
                } elseif ($this->isValidAge($birthDate)) {
                    $this->setAgeVerified();
                    $response['success'] = true;
                    $response['message'] = 'Age verified successfully';
                } else {
                    $response['message'] = str_replace('{age}', $this->minimum_age, $this->underage_text);
                    $response['redirect'] = $this->getSafeRedirectUrl();
                }
            } else {
                $confirmed = $input->post->text('age_confirmed');

                if ($confirmed === 'yes') {
                    $this->setAgeVerified();
                    $response['success'] = true;
                    $response['message'] = 'Age verified successfully';
                } else {
                    $response['message'] = str_replace('{age}', $this->minimum_age, $this->underage_text);
                    $response['redirect'] = $this->getSafeRedirectUrl();
                }
            }
        } catch (\Exception) {
            $response['message'] = 'An error occurred. Please try again.';
        }

        $this->sendJson($response);
    }

    public function addAgeVerification(HookEvent $event): void {
        if (!$this->shouldVerifyAge() || $this->isAgeVerified()) return;

        $output = $event->return;

        if (!str_contains($output, '</body>')) {
            if ($this->wire('config')->debug) {
                $this->wire('log')->warning(
                    'AgeWire: </body> not found on ' . $this->wire('page')->url . ' — overlay not injected.'
                );
            }
            return;
        }

        $event->return = str_replace(
            '</body>',
            $this->getAssets() . $this->getModalHtml() . '</body>',
            $output
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // VERIFICATION LOGIC
    // ════════════════════════════════════════════════════════════════════════

    protected function shouldVerifyAge(): bool {
        if (!$this->enabled) return false;

        $page = $this->wire('page');
        if ($page->template == 'admin') return false;

        $excludedTemplates = is_array($this->excluded_templates) ? $this->excluded_templates : [];
        if (in_array($page->template->name, $excludedTemplates)) return false;

        $excludedPages = is_array($this->excluded_pages) ? $this->excluded_pages : [];
        if (in_array($page->id, $excludedPages)) return false;

        return true;
    }

    protected function isAgeVerified(): bool {
        return $this->wire('input')->cookie($this->getSafeCookieName()) === '1';
    }

    protected function setAgeVerified(): void {
        $cookieName = $this->getSafeCookieName();

        setcookie($cookieName, '1', [
            'expires'  => time() + (int) $this->cookie_lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->wire('config')->https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Make the cookie available within the current request —
        // PW reads $_COOKIE at boot time before setcookie() is called.
        $_COOKIE[$cookieName] = '1';
    }

    protected function isValidAge(string $birthDate): bool {
        if (empty($birthDate)) return false;

        try {
            $birth = \DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$birth || $birth->format('Y-m-d') !== $birthDate) return false;
            if ($birth > new \DateTime('today')) return false;

            return (new \DateTime('today'))->diff($birth)->y >= (int) $this->minimum_age;
        } catch (\Exception) {
            return false;
        }
    }

    protected function getSafeRedirectUrl(): string {
        $url = trim((string) $this->redirect_url);
        if (str_starts_with($url, '/') || preg_match('#^https?://#i', $url)) return $url;
        return 'https://www.responsibility.org/';
    }

    protected function getSafeCookieName(): string {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $this->cookie_name);
        return $name ?: 'age_verified';
    }

    // ════════════════════════════════════════════════════════════════════════
    // ASSET & MODAL DISPATCH
    // ════════════════════════════════════════════════════════════════════════

    protected function getAssets(): string {
        return match ($this->css_framework) {
            self::FRAMEWORK_TAILWIND  => $this->getTailwindAssets(),
            self::FRAMEWORK_BOOTSTRAP => $this->getBootstrapAssets(),
            self::FRAMEWORK_UIKIT     => $this->getUikitAssets(),
            default                   => $this->getVanillaAssets(),
        };
    }

    protected function getModalHtml(): string {
        return match ($this->css_framework) {
            self::FRAMEWORK_TAILWIND  => $this->getTailwindModal(),
            self::FRAMEWORK_BOOTSTRAP => $this->getBootstrapModal(),
            self::FRAMEWORK_UIKIT     => $this->getUikitModal(),
            default                   => $this->getVanillaModal(),
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHARED HELPERS
    // ════════════════════════════════════════════════════════════════════════

    protected function sanitizedTexts(): array {
        $s   = $this->wire('sanitizer');
        $age = (int) $this->minimum_age;
        return [
            'title'   => $s->entities($this->modal_title),
            'text'    => str_replace('{age}', $age, $s->entities($this->modal_text)),
            'confirm' => str_replace('{age}', $age, $s->entities($this->confirm_button_text)),
            'deny'    => str_replace('{age}', $age, $s->entities($this->deny_button_text)),
        ];
    }

    protected function getAgreementData(): array {
        $s = $this->wire('sanitizer');
        return [
            'text'    => $s->entities($this->agreement_text),
            'privacy' => $s->entities($this->privacy_policy_url),
            'terms'   => $s->entities($this->terms_of_use_url),
        ];
    }

    protected function getCsrfVars(): string {
        $session = $this->wire('session');
        $s       = $this->wire('sanitizer');
        $name    = $s->entities($session->CSRF->getTokenName());
        $value   = $s->entities($session->CSRF->getTokenValue());
        return "var _awCsrfName='{$name}',_awCsrfValue='{$value}';";
    }

    protected function getDatePickerFields(): array {
        return match ($this->date_format) {
            'dmy' => [
                ['id' => 'aw-d1', 'label' => 'DD',   'placeholder' => 'DD',   'min' => '1',    'max' => '31',   'maxlength' => '2'],
                ['id' => 'aw-d2', 'label' => 'MM',   'placeholder' => 'MM',   'min' => '1',    'max' => '12',   'maxlength' => '2'],
                ['id' => 'aw-d3', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4'],
            ],
            'ymd' => [
                ['id' => 'aw-d1', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4'],
                ['id' => 'aw-d2', 'label' => 'MM',   'placeholder' => 'MM',   'min' => '1',    'max' => '12',   'maxlength' => '2'],
                ['id' => 'aw-d3', 'label' => 'DD',   'placeholder' => 'DD',   'min' => '1',    'max' => '31',   'maxlength' => '2'],
            ],
            default => [ // mdy
                ['id' => 'aw-d1', 'label' => 'MM',   'placeholder' => 'MM',   'min' => '1',    'max' => '12',   'maxlength' => '2'],
                ['id' => 'aw-d2', 'label' => 'DD',   'placeholder' => 'DD',   'min' => '1',    'max' => '31',   'maxlength' => '2'],
                ['id' => 'aw-d3', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4'],
            ],
        };
    }

    protected function getCustomCssTag(): string {
        if (empty($this->custom_css)) return '';
        $css = str_ireplace('</style', '<\/style', (string) $this->custom_css);
        return "<style id='aw-custom'>{$css}</style>\n";
    }

    protected function getSharedJs(): string {
        $dateFormat = $this->wire('sanitizer')->entities($this->date_format);
        $debug      = $this->wire('config')->debug ? 'true' : 'false';

        return "
(function(){
    var _debug={$debug};
    function dbg(){if(_debug&&window.console)console.log.apply(console,arguments);}
    {$this->getCsrfVars()}
    var _df='{$dateFormat}';

    document.addEventListener('DOMContentLoaded',function(){
        var overlay=document.getElementById('aw-overlay');
        if(!overlay){dbg('AgeWire: #aw-overlay not found');return;}

        var confirmBtn =document.getElementById('aw-confirm');
        var denyBtn    =document.getElementById('aw-deny');
        var d1=document.getElementById('aw-d1');
        var d2=document.getElementById('aw-d2');
        var d3=document.getElementById('aw-d3');
        var errorDiv=document.getElementById('aw-error');

        function wireInput(el,next,prev){
            if(!el)return;
            el.addEventListener('input',function(){
                var maxLen=parseInt(el.getAttribute('maxlength'));
                var maxVal=parseInt(el.getAttribute('max'));
                if(el.value.length>=maxLen){
                    if(parseInt(el.value)>maxVal)el.value=String(maxVal);
                    if(el.value.length===maxLen&&next)next.focus();
                }
            });
            if(prev)el.addEventListener('keydown',function(e){if(e.key==='Backspace'&&!el.value)prev.focus();});
        }
        wireInput(d1,d2,null);
        wireInput(d2,d3,d1);
        wireInput(d3,null,d2);

        function showError(msg){
            if(!errorDiv)return;
            errorDiv.textContent=msg;
            errorDiv.style.display='block';
        }

        function handleVerification(confirmed){
            dbg('AgeWire: verify',confirmed);
            var fd=new FormData();
            fd.append('age_verification_action','verify_age');
            fd.append('age_confirmed',confirmed);
            fd.append(_awCsrfName,_awCsrfValue);

            if(d1&&d2&&d3){
                var year,month,day;
                if(_df==='mdy'){month=d1.value.padStart(2,'0');day=d2.value.padStart(2,'0');year=d3.value;}
                else if(_df==='dmy'){day=d1.value.padStart(2,'0');month=d2.value.padStart(2,'0');year=d3.value;}
                else{year=d1.value;month=d2.value.padStart(2,'0');day=d3.value.padStart(2,'0');}
                if(month&&day&&year&&year.length===4)fd.append('birth_date',year+'-'+month+'-'+day);
            }

            if(confirmBtn)confirmBtn.disabled=true;
            if(denyBtn)denyBtn.disabled=true;

            fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){dbg('AgeWire: status',r.status);return r.json();})
            .then(function(d){
                dbg('AgeWire: response',d);
                if(d.success){
                    overlay.style.opacity='0';
                    setTimeout(function(){location.reload();},300);
                }else if(d.redirect){
                    location.href=d.redirect;
                }else{
                    showError(d.message);
                    if(confirmBtn)confirmBtn.disabled=false;
                    if(denyBtn)denyBtn.disabled=false;
                }
            })
            .catch(function(err){
                dbg('AgeWire: error',err);
                showError('An error occurred. Please try again.');
                if(confirmBtn)confirmBtn.disabled=false;
                if(denyBtn)denyBtn.disabled=false;
            });
        }

        if(confirmBtn)confirmBtn.addEventListener('click',function(e){e.preventDefault();handleVerification('yes');});
        if(denyBtn)denyBtn.addEventListener('click',function(e){e.preventDefault();handleVerification('no');});

        document.body.style.overflow='hidden';
    });
})();";
    }

    // ════════════════════════════════════════════════════════════════════════
    // VANILLA CSS RENDERER
    // ════════════════════════════════════════════════════════════════════════

    protected function getVanillaAssets(): string {
        $out  = "<style id='aw-vanilla'>\n" . $this->getVanillaCss() . "\n</style>\n";
        $out .= $this->getCustomCssTag();
        $out .= "<script>\n" . $this->getSharedJs() . "\n</script>\n";
        return $out;
    }

    protected function getVanillaCss(): string {
        return "
#aw-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;
  justify-content:center;padding:1rem;background:rgba(15,23,42,.85);
  backdrop-filter:blur(4px);transition:opacity .3s;}
#aw-box{background:#fff;border-radius:.75rem;padding:2.5rem 2rem;max-width:440px;
  width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,.35);text-align:center;}
#aw-title{margin:0 0 .75rem;font-size:1.65rem;font-weight:700;color:#0f172a;}
#aw-text{margin:0 0 1.5rem;font-size:1rem;line-height:1.6;color:#475569;}
.aw-btn{display:inline-block;padding:.75rem 1.5rem;border:none;border-radius:.5rem;
  font-size:1rem;font-weight:600;cursor:pointer;transition:all .2s;}
.aw-btn:disabled{opacity:.5;cursor:not-allowed;}
.aw-btn-confirm{background:#2563eb;color:#fff;width:100%;}
.aw-btn-confirm:hover:not(:disabled){background:#1d4ed8;}
.aw-btn-deny{background:#64748b;color:#fff;flex:1;}
.aw-btn-deny:hover:not(:disabled){background:#475569;}
.aw-btns{display:flex;gap:.75rem;flex-direction:column;}
@media(min-width:400px){.aw-btns{flex-direction:row;}}
.aw-date-wrap{margin-bottom:1.5rem;}
.aw-date-lbl{display:block;margin-bottom:.5rem;font-size:.875rem;color:#475569;}
.aw-date-row{display:flex;align-items:flex-end;justify-content:center;gap:.5rem;}
.aw-date-col{display:flex;flex-direction:column;align-items:center;}
.aw-date-col label{font-size:.7rem;margin-bottom:.25rem;color:#64748b;}
.aw-date-row input{padding:.625rem .5rem;border:2px solid #cbd5e1;border-radius:.375rem;
  font-size:1.25rem;font-weight:600;text-align:center;outline:none;
  transition:border-color .2s;-moz-appearance:textfield;}
.aw-date-row input::-webkit-outer-spin-button,
.aw-date-row input::-webkit-inner-spin-button{-webkit-appearance:none;}
.aw-date-row input:focus{border-color:#2563eb;}
.aw-d-sm{width:3.5rem;} .aw-d-lg{width:5rem;}
.aw-sep{font-size:1.5rem;font-weight:700;color:#94a3b8;padding-bottom:.25rem;}
#aw-error{display:none;margin-top:1rem;padding:.75rem;background:#fef2f2;
  border:1px solid #fecaca;border-radius:.375rem;font-size:.875rem;color:#b91c1c;}
.aw-agreement{margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e2e8f0;
  font-size:.75rem;color:#94a3b8;}
.aw-agreement a{color:#2563eb;text-decoration:none;font-weight:600;}
.aw-agreement a:hover{text-decoration:underline;}
.aw-agreement-links{display:flex;gap:.75rem;justify-content:center;margin-top:.5rem;}";
    }

    protected function getVanillaModal(): string {
        $t  = $this->sanitizedTexts();
        $ag = $this->getAgreementData();
        return "
<div id='aw-overlay'>
  <div id='aw-box'>
    <h2 id='aw-title'>{$t['title']}</h2>
    <p id='aw-text'>{$t['text']}</p>
    {$this->vanillaDatePicker()}
    {$this->vanillaButtons($t)}
    {$this->vanillaAgreement($ag)}
    <div id='aw-error'></div>
  </div>
</div>";
    }

    protected function vanillaDatePicker(): string {
        if (!$this->show_date_picker) return '';
        $label  = $this->wire('sanitizer')->entities($this->date_picker_text);
        $fields = $this->getDatePickerFields();
        $w      = fn(array $f) => $f['maxlength'] === '4' ? 'aw-d-lg' : 'aw-d-sm';

        $col = fn(array $f) => "
    <div class='aw-date-col'>
      <label>{$f['label']}</label>
      <input type='number' id='{$f['id']}' class='{$w($f)}'
        placeholder='{$f['placeholder']}' min='{$f['min']}' max='{$f['max']}' maxlength='{$f['maxlength']}' required>
    </div>";

        return "
<div class='aw-date-wrap'>
  <label class='aw-date-lbl'>{$label}</label>
  <div class='aw-date-row'>
    {$col($fields[0])}
    <span class='aw-sep'>/</span>
    {$col($fields[1])}
    <span class='aw-sep'>/</span>
    {$col($fields[2])}
  </div>
</div>";
    }

    protected function vanillaButtons(array $t): string {
        if ($this->show_date_picker) {
            return "<button type='button' id='aw-confirm' class='aw-btn aw-btn-confirm'>{$t['confirm']}</button>";
        }
        return "
<div class='aw-btns'>
  <button type='button' id='aw-confirm' class='aw-btn aw-btn-confirm'>{$t['confirm']}</button>
  <button type='button' id='aw-deny'    class='aw-btn aw-btn-deny'>{$t['deny']}</button>
</div>";
    }

    protected function vanillaAgreement(array $ag): string {
        if (!$this->show_agreement) return '';
        return "
<div class='aw-agreement'>
  <p>{$ag['text']}</p>
  <div class='aw-agreement-links'>
    <a href='{$ag['privacy']}' target='_blank' rel='noopener noreferrer'>PRIVACY POLICY</a>
    <span>|</span>
    <a href='{$ag['terms']}'   target='_blank' rel='noopener noreferrer'>TERMS OF USE</a>
  </div>
</div>";
    }

    // ════════════════════════════════════════════════════════════════════════
    // TAILWIND RENDERER
    // ════════════════════════════════════════════════════════════════════════

    protected function getTailwindAssets(): string {
        $out = '';
        if ($this->load_cdn) {
            $out .= '<script src="' . self::CDN_TAILWIND . '"></script>' . "\n";
            $out .= '<script>' . $this->getTailwindConfig() . '</script>' . "\n";
        }
        $out .= "<style id='aw-tw-base'>
.aw-date-row input::-webkit-outer-spin-button,
.aw-date-row input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.aw-date-row input[type=number]{-moz-appearance:textfield;}
</style>\n";
        $out .= $this->getCustomCssTag();
        $out .= "<script>\n" . $this->getSharedJs() . "\n</script>\n";
        return $out;
    }

    protected function getTailwindConfig(): string {
        return "tailwind.config={theme:{extend:{animation:{'fade-in':'fadeIn .3s ease-in-out','slide-up':'slideUp .4s ease-out','zoom-in':'zoomIn .3s cubic-bezier(0.34,1.56,0.64,1)','bounce-in':'bounceIn .6s cubic-bezier(0.68,-0.55,0.265,1.55)'},keyframes:{fadeIn:{'0%':{opacity:'0'},'100%':{opacity:'1'}},slideUp:{'0%':{transform:'translateY(100px)',opacity:'0'},'100%':{transform:'translateY(0)',opacity:'1'}},zoomIn:{'0%':{transform:'scale(0.7)',opacity:'0'},'100%':{transform:'scale(1)',opacity:'1'}},bounceIn:{'0%':{transform:'scale(0.3)',opacity:'0'},'50%':{transform:'scale(1.05)'},'70%':{transform:'scale(0.9)'},'100%':{transform:'scale(1)',opacity:'1'}}}}}}";
    }

    protected function getTailwindModal(): string {
        $t         = $this->sanitizedTexts();
        $ag        = $this->getAgreementData();
        $th        = $this->getTailwindThemeClasses();
        $animation = $this->getTailwindAnimationClass();

        return "
<div id='aw-overlay' class='fixed inset-0 z-[99999] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity duration-300 {$th['overlay']}'>
  <div class='relative w-full max-w-md {$animation}'>
    <div class='{$th['container']}'>
      <h2 class='{$th['title']} text-center'>{$t['title']}</h2>
      <p class='{$th['text']} text-center'>{$t['text']}</p>
      {$this->tailwindDatePicker($th)}
      {$this->tailwindButtons($t, $th)}
      {$this->tailwindAgreement($ag, $th)}
      <div id='aw-error' class='hidden mt-4 p-3 {$th['error']} rounded text-sm text-center' style='display:none'></div>
    </div>
  </div>
</div>";
    }

    protected function tailwindDatePicker(array $th): string {
        if (!$this->show_date_picker) return '';
        $label  = $this->wire('sanitizer')->entities($this->date_picker_text);
        $fields = $this->getDatePickerFields();
        $flex   = fn(array $f) => $f['maxlength'] === '4' ? 'flex-[1.5]' : 'flex-1';

        $col = fn(array $f) => "
      <div class='{$flex($f)}'>
        <label class='block text-xs text-center mb-1 {$th['label']}'>{$f['label']}</label>
        <input type='number' id='{$f['id']}' class='aw-date-row w-full {$th['input']}'
          placeholder='{$f['placeholder']}' min='{$f['min']}' max='{$f['max']}' maxlength='{$f['maxlength']}' required>
      </div>";

        return "
<div class='mb-6'>
  <label class='text-center block {$th['label']}'>{$label}</label>
  <div class='flex items-center justify-center gap-2 mt-3 max-w-xs mx-auto'>
    {$col($fields[0])}
    <span class='mt-6 {$th['separator']}'>/</span>
    {$col($fields[1])}
    <span class='mt-6 {$th['separator']}'>/</span>
    {$col($fields[2])}
  </div>
</div>";
    }

    protected function tailwindButtons(array $t, array $th): string {
        $base = "font-semibold text-base py-3 px-6 rounded transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed";
        if ($this->show_date_picker) {
            return "<button type='button' id='aw-confirm' class='w-full {$base} {$th['button']}'>{$t['confirm']}</button>";
        }
        return "
<div class='flex flex-col sm:flex-row gap-3'>
  <button type='button' id='aw-confirm' class='flex-1 {$base} {$th['button']}'>{$t['confirm']}</button>
  <button type='button' id='aw-deny'    class='flex-1 bg-gray-500 hover:bg-gray-600 text-white {$base}'>{$t['deny']}</button>
</div>";
    }

    protected function tailwindAgreement(array $ag, array $th): string {
        if (!$this->show_agreement) return '';
        return "
<div class='mt-6 pt-4 border-t {$th['agreement_border']}'>
  <p class='text-xs text-center {$th['agreement_text']} mb-2'>{$ag['text']}</p>
  <div class='flex items-center justify-center gap-3 text-xs'>
    <a href='{$ag['privacy']}' target='_blank' rel='noopener noreferrer' class='{$th['agreement_link']} hover:underline font-medium'>PRIVACY POLICY</a>
    <span class='{$th['agreement_separator']}'>|</span>
    <a href='{$ag['terms']}'   target='_blank' rel='noopener noreferrer' class='{$th['agreement_link']} hover:underline font-medium'>TERMS OF USE</a>
  </div>
</div>";
    }

    protected function getTailwindAnimationClass(): string {
        return match ($this->animation_style) {
            'slide'  => 'animate-slide-up',
            'zoom'   => 'animate-zoom-in',
            'bounce' => 'animate-bounce-in',
            default  => 'animate-fade-in',
        };
    }

    protected function getTailwindThemeClasses(): array {
        $bc = "p-8 border shadow-2xl rounded-lg";
        $bt = "text-3xl font-bold mb-4";
        $bx = "text-lg mb-6 leading-relaxed";

        return match ($this->theme_style) {
            'dark'      => ['overlay'=>'bg-black','container'=>"{$bc} bg-black border-zinc-800",'title'=>"{$bt} text-white",'text'=>"{$bx} text-zinc-300",'button'=>'bg-zinc-700 hover:bg-zinc-600 text-white','input'=>'px-4 py-3 bg-zinc-900 border-2 border-zinc-700 text-white text-center text-xl font-semibold rounded focus:ring-2 focus:ring-zinc-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-zinc-400 mb-2','separator'=>'text-zinc-500 text-2xl font-bold','error'=>'bg-red-950 border border-red-800 text-red-300','agreement_border'=>'border-zinc-800','agreement_text'=>'text-zinc-500','agreement_link'=>'text-zinc-400 hover:text-zinc-300','agreement_separator'=>'text-zinc-700'],
            'minimal'   => ['overlay'=>'bg-gray-900/50','container'=>"{$bc} bg-white border-gray-200",'title'=>"text-2xl font-semibold text-gray-900 mb-3",'text'=>"text-base text-gray-600 mb-6",'button'=>'bg-gray-900 hover:bg-gray-800 text-white','input'=>'px-4 py-3 bg-white border-2 border-gray-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-gray-700 mb-2','separator'=>'text-gray-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-200 text-red-700','agreement_border'=>'border-gray-200','agreement_text'=>'text-gray-500','agreement_link'=>'text-gray-700 hover:text-gray-900','agreement_separator'=>'text-gray-300'],
            'classic'   => ['overlay'=>'bg-black/70','container'=>"{$bc} bg-white border-gray-300",'title'=>"{$bt} text-gray-900",'text'=>"{$bx} text-gray-700",'button'=>'bg-blue-700 hover:bg-blue-800 text-white','input'=>'px-4 py-3 bg-gray-50 border-2 border-gray-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-blue-700 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-gray-700 mb-2','separator'=>'text-gray-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-200 text-red-700','agreement_border'=>'border-gray-200','agreement_text'=>'text-gray-500','agreement_link'=>'text-blue-700 hover:text-blue-800','agreement_separator'=>'text-gray-300'],
            'gradient'  => ['overlay'=>'bg-gradient-to-br from-purple-900/90 via-blue-900/90 to-pink-900/90','container'=>"{$bc} bg-white/95 backdrop-blur-xl border-white/20",'title'=>"{$bt} bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent",'text'=>"{$bx} text-gray-700",'button'=>'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white','input'=>'px-4 py-3 bg-white/80 border-2 border-purple-200 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-gray-700 mb-2','separator'=>'text-purple-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-200 text-red-700','agreement_border'=>'border-purple-200','agreement_text'=>'text-gray-500','agreement_link'=>'text-purple-600 hover:text-pink-600','agreement_separator'=>'text-purple-200'],
            'neon'      => ['overlay'=>'bg-black/95','container'=>"{$bc} bg-zinc-900 border-cyan-500 shadow-[0_0_30px_rgba(6,182,212,0.3)]",'title'=>"{$bt} text-cyan-400 drop-shadow-[0_0_10px_rgba(6,182,212,0.8)]",'text'=>"{$bx} text-cyan-100",'button'=>'bg-cyan-500 hover:bg-cyan-400 text-black font-bold shadow-[0_0_20px_rgba(6,182,212,0.5)]','input'=>'px-4 py-3 bg-zinc-800 border-2 border-cyan-500 text-cyan-100 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-cyan-400 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-cyan-400 mb-2','separator'=>'text-cyan-500 text-2xl font-bold','error'=>'bg-red-950 border border-red-500 text-red-300','agreement_border'=>'border-cyan-900','agreement_text'=>'text-cyan-600','agreement_link'=>'text-cyan-400 hover:text-cyan-300','agreement_separator'=>'text-cyan-800'],
            'elegant'   => ['overlay'=>'bg-slate-900/90','container'=>"{$bc} bg-gradient-to-br from-slate-50 to-slate-100 border-slate-200",'title'=>"{$bt} text-slate-800 font-serif",'text'=>"{$bx} text-slate-600 font-serif",'button'=>'bg-slate-800 hover:bg-slate-700 text-slate-50','input'=>'px-4 py-3 bg-white border-2 border-slate-300 text-slate-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-slate-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-slate-600 mb-2 font-serif','separator'=>'text-slate-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-300 text-red-800','agreement_border'=>'border-slate-200','agreement_text'=>'text-slate-500 font-serif','agreement_link'=>'text-slate-700 hover:text-slate-900','agreement_separator'=>'text-slate-300'],
            'corporate' => ['overlay'=>'bg-slate-800/90','container'=>"{$bc} bg-white border-slate-300",'title'=>"{$bt} text-slate-900",'text'=>"{$bx} text-slate-700",'button'=>'bg-indigo-600 hover:bg-indigo-700 text-white','input'=>'px-4 py-3 bg-slate-50 border-2 border-slate-300 text-slate-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-slate-700 mb-2','separator'=>'text-slate-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-200 text-red-700','agreement_border'=>'border-slate-200','agreement_text'=>'text-slate-500','agreement_link'=>'text-indigo-600 hover:text-indigo-700','agreement_separator'=>'text-slate-300'],
            'vibrant'   => ['overlay'=>'bg-gradient-to-br from-orange-500/80 via-pink-500/80 to-purple-600/80','container'=>"{$bc} bg-white border-white/50",'title'=>"{$bt} text-transparent bg-clip-text bg-gradient-to-r from-orange-600 to-pink-600",'text'=>"{$bx} text-gray-800",'button'=>'bg-gradient-to-r from-orange-500 to-pink-500 hover:from-orange-600 hover:to-pink-600 text-white','input'=>'px-4 py-3 bg-orange-50 border-2 border-orange-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-gray-700 mb-2','separator'=>'text-orange-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-300 text-red-800','agreement_border'=>'border-orange-200','agreement_text'=>'text-gray-500','agreement_link'=>'text-orange-600 hover:text-pink-600','agreement_separator'=>'text-orange-200'],
            'nature'    => ['overlay'=>'bg-gradient-to-br from-emerald-900/90 via-green-800/90 to-teal-900/90','container'=>"{$bc} bg-white border-green-200",'title'=>"{$bt} text-green-800",'text'=>"{$bx} text-green-900",'button'=>'bg-green-600 hover:bg-green-700 text-white','input'=>'px-4 py-3 bg-green-50 border-2 border-green-300 text-green-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-green-700 mb-2','separator'=>'text-green-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-300 text-red-800','agreement_border'=>'border-green-200','agreement_text'=>'text-green-600','agreement_link'=>'text-green-700 hover:text-green-800','agreement_separator'=>'text-green-200'],
            'sunset'    => ['overlay'=>'bg-gradient-to-br from-orange-600/85 via-red-500/85 to-pink-600/85','container'=>"{$bc} bg-white/95 backdrop-blur-sm border-orange-200",'title'=>"{$bt} text-orange-800",'text'=>"{$bx} text-orange-900",'button'=>'bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white','input'=>'px-4 py-3 bg-orange-50 border-2 border-orange-300 text-orange-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-orange-700 mb-2','separator'=>'text-orange-400 text-2xl font-bold','error'=>'bg-red-100 border border-red-300 text-red-800','agreement_border'=>'border-orange-200','agreement_text'=>'text-orange-600','agreement_link'=>'text-orange-700 hover:text-red-600','agreement_separator'=>'text-orange-200'],
            'ocean'     => ['overlay'=>'bg-gradient-to-br from-blue-900/90 via-cyan-800/90 to-teal-900/90','container'=>"{$bc} bg-white border-blue-200",'title'=>"{$bt} text-blue-900",'text'=>"{$bx} text-cyan-900",'button'=>'bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white','input'=>'px-4 py-3 bg-blue-50 border-2 border-blue-300 text-blue-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-blue-700 mb-2','separator'=>'text-blue-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-300 text-red-800','agreement_border'=>'border-blue-200','agreement_text'=>'text-cyan-600','agreement_link'=>'text-blue-700 hover:text-cyan-700','agreement_separator'=>'text-blue-200'],
            'purple'    => ['overlay'=>'bg-gradient-to-br from-purple-900/90 via-violet-800/90 to-indigo-900/90','container'=>"{$bc} bg-white border-purple-200",'title'=>"{$bt} text-purple-900",'text'=>"{$bx} text-purple-800",'button'=>'bg-purple-600 hover:bg-purple-700 text-white','input'=>'px-4 py-3 bg-purple-50 border-2 border-purple-300 text-purple-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-purple-700 mb-2','separator'=>'text-purple-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-300 text-red-800','agreement_border'=>'border-purple-200','agreement_text'=>'text-purple-600','agreement_link'=>'text-purple-700 hover:text-purple-800','agreement_separator'=>'text-purple-200'],
            default     => ['overlay'=>'bg-slate-900/80','container'=>"{$bc} bg-white border-gray-100",'title'=>"{$bt} text-gray-900",'text'=>"{$bx} text-gray-700",'button'=>'bg-blue-600 hover:bg-blue-700 text-white','input'=>'px-4 py-3 bg-gray-50 border-2 border-gray-200 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all','label'=>'block text-sm font-medium text-gray-600 mb-2','separator'=>'text-gray-400 text-2xl font-bold','error'=>'bg-red-50 border border-red-200 text-red-700','agreement_border'=>'border-gray-200','agreement_text'=>'text-gray-500','agreement_link'=>'text-blue-600 hover:text-blue-700','agreement_separator'=>'text-gray-300'],
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    // BOOTSTRAP RENDERER
    // ════════════════════════════════════════════════════════════════════════

    protected function getBootstrapAssets(): string {
        $out = '';
        if ($this->load_cdn) {
            $out .= "<link rel='stylesheet' href='" . self::CDN_BOOTSTRAP_CSS . "'>\n";
            $out .= "<script src='" . self::CDN_BOOTSTRAP_JS . "'></script>\n";
        }
        $out .= "<style id='aw-bs'>
#aw-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;
  justify-content:center;padding:1rem;background:rgba(15,23,42,.8);
  backdrop-filter:blur(4px);transition:opacity .3s;}
#aw-box{max-width:440px;width:100%;text-align:center;}
.aw-date-row input::-webkit-outer-spin-button,
.aw-date-row input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.aw-date-row input[type=number]{-moz-appearance:textfield;}
#aw-error{display:none;}
</style>\n";
        $out .= $this->getCustomCssTag();
        $out .= "<script>\n" . $this->getSharedJs() . "\n</script>\n";
        return $out;
    }

    protected function getBootstrapModal(): string {
        $t  = $this->sanitizedTexts();
        $ag = $this->getAgreementData();
        return "
<div id='aw-overlay'>
  <div id='aw-box'>
    <div class='card shadow-lg border-0 rounded-4'>
      <div class='card-body p-4 p-md-5'>
        <h2 class='card-title fw-bold mb-3 h4'>{$t['title']}</h2>
        <p class='card-text text-secondary mb-4'>{$t['text']}</p>
        {$this->bootstrapDatePicker()}
        {$this->bootstrapButtons($t)}
        {$this->bootstrapAgreement($ag)}
        <div id='aw-error' class='alert alert-danger mt-3 py-2 small'></div>
      </div>
    </div>
  </div>
</div>";
    }

    protected function bootstrapDatePicker(): string {
        if (!$this->show_date_picker) return '';
        $label  = $this->wire('sanitizer')->entities($this->date_picker_text);
        $fields = $this->getDatePickerFields();
        $w      = fn(array $f) => $f['maxlength'] === '4' ? 'col-5' : 'col-3';

        $col = fn(array $f) => "
      <div class='{$w($f)}'>
        <label class='form-label small text-center d-block mb-1'>{$f['label']}</label>
        <input type='number' id='{$f['id']}' class='form-control text-center fw-bold fs-5 aw-date-row'
          placeholder='{$f['placeholder']}' min='{$f['min']}' max='{$f['max']}' maxlength='{$f['maxlength']}' required>
      </div>";

        return "
<div class='mb-4'>
  <label class='form-label text-secondary small'>{$label}</label>
  <div class='row g-2 justify-content-center align-items-end'>
    {$col($fields[0])}
    <div class='col-auto align-self-center pb-1'><span class='text-secondary fw-bold fs-4'>/</span></div>
    {$col($fields[1])}
    <div class='col-auto align-self-center pb-1'><span class='text-secondary fw-bold fs-4'>/</span></div>
    {$col($fields[2])}
  </div>
</div>";
    }

    protected function bootstrapButtons(array $t): string {
        if ($this->show_date_picker) {
            return "<button type='button' id='aw-confirm' class='btn btn-primary w-100 py-2 fw-semibold'>{$t['confirm']}</button>";
        }
        return "
<div class='d-grid gap-2 d-sm-flex'>
  <button type='button' id='aw-confirm' class='btn btn-primary flex-fill py-2 fw-semibold'>{$t['confirm']}</button>
  <button type='button' id='aw-deny'    class='btn btn-secondary flex-fill py-2 fw-semibold'>{$t['deny']}</button>
</div>";
    }

    protected function bootstrapAgreement(array $ag): string {
        if (!$this->show_agreement) return '';
        return "
<div class='mt-4 pt-3 border-top'>
  <p class='small text-muted mb-2'>{$ag['text']}</p>
  <div class='d-flex justify-content-center gap-3 small'>
    <a href='{$ag['privacy']}' target='_blank' rel='noopener noreferrer' class='text-decoration-none fw-semibold'>PRIVACY POLICY</a>
    <span class='text-muted'>|</span>
    <a href='{$ag['terms']}'   target='_blank' rel='noopener noreferrer' class='text-decoration-none fw-semibold'>TERMS OF USE</a>
  </div>
</div>";
    }

    // ════════════════════════════════════════════════════════════════════════
    // UIKIT RENDERER
    // ════════════════════════════════════════════════════════════════════════

    protected function getUikitAssets(): string {
        $out = '';
        if ($this->load_cdn) {
            $out .= "<link rel='stylesheet' href='" . self::CDN_UIKIT_CSS . "'>\n";
            $out .= "<script src='" . self::CDN_UIKIT_JS . "'></script>\n";
            $out .= "<script src='" . self::CDN_UIKIT_ICONS . "'></script>\n";
        }
        $out .= "<style id='aw-uk'>
#aw-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;
  justify-content:center;padding:1rem;background:rgba(15,23,42,.8);
  backdrop-filter:blur(4px);transition:opacity .3s;}
.aw-date-row input::-webkit-outer-spin-button,
.aw-date-row input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
.aw-date-row input[type=number]{-moz-appearance:textfield;}
#aw-error{display:none;}
</style>\n";
        $out .= $this->getCustomCssTag();
        $out .= "<script>\n" . $this->getSharedJs() . "\n</script>\n";
        return $out;
    }

    protected function getUikitModal(): string {
        $t  = $this->sanitizedTexts();
        $ag = $this->getAgreementData();
        return "
<div id='aw-overlay'>
  <div class='uk-card uk-card-default uk-card-body uk-border-rounded' style='max-width:440px;width:100%;text-align:center;'>
    <h2 class='uk-card-title uk-text-bold uk-margin-small-bottom'>{$t['title']}</h2>
    <p class='uk-text-muted uk-margin-medium-bottom'>{$t['text']}</p>
    {$this->uikitDatePicker()}
    {$this->uikitButtons($t)}
    {$this->uikitAgreement($ag)}
    <div id='aw-error' class='uk-alert-danger uk-margin-top' style='padding:.5rem .75rem;border-radius:4px;'></div>
  </div>
</div>";
    }

    protected function uikitDatePicker(): string {
        if (!$this->show_date_picker) return '';
        $label  = $this->wire('sanitizer')->entities($this->date_picker_text);
        $fields = $this->getDatePickerFields();
        $w      = fn(array $f) => $f['maxlength'] === '4' ? '5rem' : '3.5rem';

        $col = fn(array $f) => "
    <div style='display:flex;flex-direction:column;align-items:center;'>
      <label class='uk-text-small' style='margin-bottom:.25rem;'>{$f['label']}</label>
      <input type='number' id='{$f['id']}' class='uk-input uk-text-center uk-text-bold aw-date-row'
        style='width:{$w($f)};font-size:1.25rem;padding:.5rem;'
        placeholder='{$f['placeholder']}' min='{$f['min']}' max='{$f['max']}' maxlength='{$f['maxlength']}' required>
    </div>";

        return "
<div class='uk-margin-medium-bottom'>
  <label class='uk-text-small uk-text-muted'>{$label}</label>
  <div style='display:flex;align-items:flex-end;justify-content:center;gap:.5rem;margin-top:.5rem;'>
    {$col($fields[0])}
    <span class='uk-text-bold uk-text-large uk-text-muted' style='padding-bottom:.4rem'>/</span>
    {$col($fields[1])}
    <span class='uk-text-bold uk-text-large uk-text-muted' style='padding-bottom:.4rem'>/</span>
    {$col($fields[2])}
  </div>
</div>";
    }

    protected function uikitButtons(array $t): string {
        if ($this->show_date_picker) {
            return "<button type='button' id='aw-confirm' class='uk-button uk-button-primary uk-width-1-1 uk-border-rounded'>{$t['confirm']}</button>";
        }
        return "
<div class='uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s' uk-grid>
  <div><button type='button' id='aw-confirm' class='uk-button uk-button-primary  uk-width-1-1 uk-border-rounded'>{$t['confirm']}</button></div>
  <div><button type='button' id='aw-deny'    class='uk-button uk-button-default  uk-width-1-1 uk-border-rounded'>{$t['deny']}</button></div>
</div>";
    }

    protected function uikitAgreement(array $ag): string {
        if (!$this->show_agreement) return '';
        return "
<div class='uk-margin-top' style='padding-top:.75rem;border-top:1px solid #e5e5e5;'>
  <p class='uk-text-small uk-text-muted uk-margin-small-bottom'>{$ag['text']}</p>
  <div style='display:flex;justify-content:center;gap:.75rem;'>
    <a href='{$ag['privacy']}' target='_blank' rel='noopener noreferrer' class='uk-text-small uk-text-bold uk-link-reset uk-link-text'>PRIVACY POLICY</a>
    <span class='uk-text-muted'>|</span>
    <a href='{$ag['terms']}'   target='_blank' rel='noopener noreferrer' class='uk-text-small uk-text-bold uk-link-reset uk-link-text'>TERMS OF USE</a>
  </div>
</div>";
    }

    // ════════════════════════════════════════════════════════════════════════
    // MODULE CONFIG INPUTFIELDS
    // ════════════════════════════════════════════════════════════════════════

    public static function getModuleConfigInputfields(array $data): InputfieldWrapper {
        $data        = array_merge(self::$defaultConfig, $data);
        $inputfields = new InputfieldWrapper();
        $m           = wire('modules');

        // ════════════════════════════════════════════════════════════════════
        // ROW 1 — General (left 50%) + Framework & Theme (right 50%)
        // ════════════════════════════════════════════════════════════════════

        // ── General ─────────────────────────────────────────────────────────
        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'General Settings';
        $fs->collapsed = false;
        $fs->columnWidth = 50;

            $f = $m->get('InputfieldCheckbox');
            $f->name = 'enabled'; $f->label = 'Enable Age Verification';
            $f->description = 'Enable or disable age verification site-wide.';
            $f->checked = $data['enabled'] ? 'checked' : '';
            $fs->add($f);

            // Minimum Age + Cookie Name — side by side inside the fieldset
            $f = $m->get('InputfieldInteger');
            $f->name = 'minimum_age'; $f->label = 'Minimum Age';
            $f->description = 'Required age to access the site.';
            $f->value = $data['minimum_age']; $f->min = 1; $f->max = 100;
            $f->columnWidth = 40;
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'cookie_name'; $f->label = 'Cookie Name';
            $f->description = 'Letters, numbers, hyphens and underscores only.';
            $f->value = $data['cookie_name']; $f->pattern = '[a-zA-Z0-9_\-]+';
            $f->columnWidth = 60;
            $fs->add($f);

            $f = $m->get('InputfieldInteger');
            $f->name = 'cookie_lifetime'; $f->label = 'Cookie Lifetime (seconds)';
            $f->description = "1 day = 86400 · 7 days = 604800 · 30 days = 2592000 · 90 days = 7776000";
            $f->value = $data['cookie_lifetime']; $f->min = 60;
            $f->notes = 'Recommended: 2592000 (30 days).';
            $fs->add($f);

            $f = $m->get('InputfieldURL');
            $f->name = 'redirect_url'; $f->label = 'Redirect URL for underage users';
            $f->description = 'Must start with http://, https://, or /.';
            $f->notes = 'Yes/No mode is a UX-only barrier — use date picker for stronger protection.';
            $f->value = $data['redirect_url'];
            $fs->add($f);

        $inputfields->add($fs);

        // ── Framework & Theme ────────────────────────────────────────────────
        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'Framework & Theme';
        $fs->collapsed = false;
        $fs->columnWidth = 50;

            $f = $m->get('InputfieldSelect');
            $f->name = 'css_framework'; $f->label = 'CSS Framework';
            $f->description = 'Framework used to render the modal.';
            $f->addOption('vanilla',   'Vanilla CSS — self-contained, no dependencies');
            $f->addOption('tailwind',  'Tailwind CSS — utility-first, multiple colour themes');
            $f->addOption('bootstrap', 'Bootstrap 5 — uses your existing Bootstrap styles');
            $f->addOption('uikit',     'UIkit 3 — uses your existing UIkit styles');
            $f->value = $data['css_framework'];
            $fs->add($f);

            $f = $m->get('InputfieldCheckbox');
            $f->name = 'load_cdn'; $f->label = 'Load Framework from CDN';
            $f->description = 'Disable if the framework is already loaded by your templates.';
            $f->notes = 'Tailwind: Play CDN (JIT, dev/low-traffic only — compile static CSS for production). Bootstrap & UIkit: jsDelivr.';
            $f->showIf = 'css_framework!=vanilla';
            $f->checked = $data['load_cdn'] ? 'checked' : '';
            $fs->add($f);

            // Theme + Animation — side by side
            $f = $m->get('InputfieldSelect');
            $f->name = 'theme_style'; $f->label = 'Theme';
            $f->description = 'Applies only when Tailwind is selected.';
            $f->showIf = 'css_framework=tailwind';
            $f->columnWidth = 60;
            foreach ([
                'modern'    => 'Modern — Clean blue',
                'dark'      => 'Dark — Black/zinc',
                'classic'   => 'Classic — Traditional blue',
                'minimal'   => 'Minimal — Monochrome',
                'gradient'  => 'Gradient — Purple/pink',
                'neon'      => 'Neon — Cyberpunk cyan',
                'elegant'   => 'Elegant — Slate/serif',
                'corporate' => 'Corporate — Indigo',
                'vibrant'   => 'Vibrant — Orange/pink',
                'nature'    => 'Nature — Green',
                'sunset'    => 'Sunset — Orange/red',
                'ocean'     => 'Ocean — Blue/cyan',
                'purple'    => 'Purple',
            ] as $v => $l) $f->addOption($v, $l);
            $f->value = $data['theme_style'];
            $fs->add($f);

            $f = $m->get('InputfieldSelect');
            $f->name = 'animation_style'; $f->label = 'Animation';
            $f->description = 'Applies only when Tailwind is selected.';
            $f->showIf = 'css_framework=tailwind';
            $f->columnWidth = 40;
            $f->addOption('fade',   'Fade In');
            $f->addOption('slide',  'Slide Up');
            $f->addOption('zoom',   'Zoom In');
            $f->addOption('bounce', 'Bounce In');
            $f->value = $data['animation_style'];
            $fs->add($f);

            $f = $m->get('InputfieldTextarea');
            $f->name = 'custom_css'; $f->label = 'Custom CSS';
            $f->description = 'Injected after framework styles. Works with any framework.';
            $f->value = $data['custom_css']; $f->rows = 5;
            $fs->add($f);

        $inputfields->add($fs);

        // ════════════════════════════════════════════════════════════════════
        // ROW 2 — Modal Content (full width)
        // Title 30% | Body Text 40% | Confirm 15% | Deny 15%
        // ════════════════════════════════════════════════════════════════════

        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'Modal Content';
        $fs->collapsed = Inputfield::collapsedYes;

            $f = $m->get('InputfieldText');
            $f->name = 'modal_title'; $f->label = 'Title';
            $f->value = $data['modal_title'];
            $f->columnWidth = 30;
            $fs->add($f);

            $f = $m->get('InputfieldTextarea');
            $f->name = 'modal_text'; $f->label = 'Body Text';
            $f->description = 'Use {age} for minimum age.';
            $f->value = $data['modal_text']; $f->rows = 2;
            $f->columnWidth = 40;
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'confirm_button_text'; $f->label = 'Confirm Button';
            $f->description = 'Use {age}.';
            $f->value = $data['confirm_button_text'];
            $f->columnWidth = 15;
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'deny_button_text'; $f->label = 'Deny Button';
            $f->description = 'Use {age}.';
            $f->value = $data['deny_button_text'];
            $f->columnWidth = 15;
            $fs->add($f);

        $inputfields->add($fs);

        // ════════════════════════════════════════════════════════════════════
        // ROW 3 — Date Picker (left 50%) + Agreement (right 50%)
        // ════════════════════════════════════════════════════════════════════

        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'Date Picker';
        $fs->collapsed = Inputfield::collapsedYes;
        $fs->columnWidth = 50;

            $f = $m->get('InputfieldCheckbox');
            $f->name = 'show_date_picker'; $f->label = 'Show Date Picker';
            $f->description = 'Separate date inputs instead of Yes/No buttons. Better bot protection.';
            $f->checked = $data['show_date_picker'] ? 'checked' : '';
            $fs->add($f);

            $f = $m->get('InputfieldSelect');
            $f->name = 'date_format'; $f->label = 'Date Format';
            $f->addOption('mdy', 'MM/DD/YYYY (American)');
            $f->addOption('dmy', 'DD/MM/YYYY (European)');
            $f->addOption('ymd', 'YYYY/MM/DD (ISO)');
            $f->value = $data['date_format'];
            $f->showIf = 'show_date_picker=1';
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'date_picker_text'; $f->label = 'Picker Label';
            $f->description = 'Text shown above the date fields.';
            $f->value = $data['date_picker_text'];
            $f->showIf = 'show_date_picker=1';
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'invalid_date_text'; $f->label = 'Invalid Date Message';
            $f->value = $data['invalid_date_text'];
            $f->showIf = 'show_date_picker=1';
            $f->columnWidth = 50;
            $fs->add($f);

            $f = $m->get('InputfieldText');
            $f->name = 'underage_text'; $f->label = 'Underage Message';
            $f->description = 'Use {age}.';
            $f->value = $data['underage_text'];
            $f->showIf = 'show_date_picker=1';
            $f->columnWidth = 50;
            $fs->add($f);

        $inputfields->add($fs);

        // ── Agreement ────────────────────────────────────────────────────────
        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'Terms & Privacy Agreement';
        $fs->collapsed = Inputfield::collapsedYes;
        $fs->columnWidth = 50;

            $f = $m->get('InputfieldCheckbox');
            $f->name = 'show_agreement'; $f->label = 'Show Agreement';
            $f->description = 'Display terms and privacy links at the bottom of the modal.';
            $f->checked = $data['show_agreement'] ? 'checked' : '';
            $fs->add($f);

            $f = $m->get('InputfieldTextarea');
            $f->name = 'agreement_text'; $f->label = 'Agreement Text';
            $f->value = $data['agreement_text']; $f->rows = 2;
            $f->showIf = 'show_agreement=1';
            $fs->add($f);

            $f = $m->get('InputfieldURL');
            $f->name = 'privacy_policy_url'; $f->label = 'Privacy Policy URL';
            $f->value = $data['privacy_policy_url'];
            $f->showIf = 'show_agreement=1';
            $f->columnWidth = 50;
            $fs->add($f);

            $f = $m->get('InputfieldURL');
            $f->name = 'terms_of_use_url'; $f->label = 'Terms of Use URL';
            $f->value = $data['terms_of_use_url'];
            $f->showIf = 'show_agreement=1';
            $f->columnWidth = 50;
            $fs->add($f);

        $inputfields->add($fs);

        // ════════════════════════════════════════════════════════════════════
        // ROW 4 — Exclusions (full width, Templates 50% | Pages 50%)
        // ════════════════════════════════════════════════════════════════════

        $fs = $m->get('InputfieldFieldset');
        $fs->label = 'Exclusions';
        $fs->collapsed = Inputfield::collapsedYes;

            $f = $m->get('InputfieldAsmSelect');
            $f->name = 'excluded_templates'; $f->label = 'Excluded Templates';
            $f->description = 'Age verification will not appear on pages with these templates.';
            $f->columnWidth = 50;
            foreach (wire('templates') as $tpl) {
                if ($tpl->name !== 'admin') $f->addOption($tpl->name, $tpl->name);
            }
            $f->value = $data['excluded_templates'];
            $fs->add($f);

            $f = $m->get('InputfieldPageListSelectMultiple');
            $f->name = 'excluded_pages'; $f->label = 'Excluded Pages';
            $f->description = 'Age verification will not appear on these specific pages.';
            $f->columnWidth = 50;
            $f->value = $data['excluded_pages'];
            $fs->add($f);

        $inputfields->add($fs);

        return $inputfields;
    }
}
