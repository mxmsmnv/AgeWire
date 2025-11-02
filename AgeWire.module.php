<?php namespace ProcessWire;

class AgeWire extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return array(
            'title' => 'AgeWire',
            'summary' => 'Age verification module with Tailwind CSS support',
            'version' => '1.0.9',
            'author' => 'Maxim Alex',
            'href' => '',
            'singular' => true,
            'autoload' => true,
            'icon' => 'calendar-check-o'
        );
    }

    protected static $defaultConfig = array(
        'enabled' => 1,
        'minimum_age' => 18,
        'cookie_name' => 'age_verified',
        'cookie_lifetime' => 2592000, // 30 days by default
        'modal_title' => 'Please verify your age',
        'modal_text' => 'You must be {age} years or older to access this website.',
        'confirm_button_text' => 'I am {age} or older',
        'deny_button_text' => 'I am under {age}',
        'redirect_url' => 'http://responsibility.org/',
        'excluded_templates' => array(),
        'excluded_pages' => array(),
        'show_date_picker' => 0,
        'date_format' => 'mdy', // mm/dd/yyyy
        'date_picker_text' => 'Please enter your date of birth:',
        'invalid_date_text' => 'Please enter a valid date of birth.',
        'underage_text' => 'Sorry, you must be {age} years or older to access this website.',
        'show_agreement' => 1,
        'agreement_text' => 'By submitting this form, you agree to be bound by the Terms of Use and Privacy Policy',
        'privacy_policy_url' => '/privacy-policy/',
        'terms_of_use_url' => '/terms-of-use/',
        'theme_style' => 'modern',
        'animation_style' => 'fade',
        'load_external_css' => 1,
        'custom_css' => ''
    );

    public function init() {
        $this->addHookBefore('ProcessPageView::execute', $this, 'handleAjaxVerification');
        
        if($this->enabled) {
            $this->addHookAfter('Page::render', $this, 'addAgeVerification');
        }
    }

    public function handleAjaxVerification(HookEvent $event) {
        $input = $this->wire('input');
        
        if($input->post('age_verification_action') === 'verify_age') {
            $this->processAgeVerification();
            exit;
        }
    }

    protected function processAgeVerification() {
        $input = $this->wire('input');
        $response = array('success' => false, 'message' => '');
        
        try {
            if($this->show_date_picker) {
                $birthDate = $input->post->text('birth_date');
                
                if(empty($birthDate)) {
                    $response['message'] = $this->invalid_date_text;
                } elseif($this->isValidAge($birthDate)) {
                    $this->setAgeVerified();
                    $response['success'] = true;
                    $response['message'] = 'Age verified successfully';
                } else {
                    $response['message'] = str_replace('{age}', $this->minimum_age, $this->underage_text);
                    $response['redirect'] = $this->redirect_url;
                }
            } else {
                $confirmed = $input->post->text('age_confirmed');
                
                if($confirmed === 'yes') {
                    $this->setAgeVerified();
                    $response['success'] = true;
                    $response['message'] = 'Age verified successfully';
                } else {
                    $response['message'] = str_replace('{age}', $this->minimum_age, $this->underage_text);
                    $response['redirect'] = $this->redirect_url;
                }
            }
        } catch(\Exception $e) {
            $response['message'] = 'An error occurred. Please try again.';
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function addAgeVerification(HookEvent $event) {
        if(!$this->shouldVerifyAge() || $this->isAgeVerified()) {
            return;
        }

        $output = $event->return;
        
        if(strpos($output, '</body>') === false) {
            return;
        }

        $assets = $this->getAssets();
        $modal = $this->getModalHtml();

        $output = str_replace('</body>', $assets . $modal . '</body>', $output);
        $event->return = $output;
    }

    protected function shouldVerifyAge() {
        if(!$this->enabled) return false;

        $page = $this->wire('page');
        
        if($page->template == 'admin') return false;

        $excludedTemplates = is_array($this->excluded_templates) ? $this->excluded_templates : array();
        if(in_array($page->template->name, $excludedTemplates)) return false;

        $excludedPages = is_array($this->excluded_pages) ? $this->excluded_pages : array();
        if(in_array($page->id, $excludedPages)) return false;

        return true;
    }

    protected function isAgeVerified() {
        return $this->wire('input')->cookie($this->cookie_name) === '1';
    }

    protected function setAgeVerified() {
        $expire = time() + (int)$this->cookie_lifetime;
        
        setcookie($this->cookie_name, '1', array(
            'expires' => $expire,
            'path' => '/',
            'domain' => '',
            'secure' => $this->wire('config')->https,
            'httponly' => true,
            'samesite' => 'Lax'
        ));
        
        $_COOKIE[$this->cookie_name] = '1';
    }

    protected function isValidAge($birthDate) {
        if(empty($birthDate)) return false;

        try {
            $birth = new \DateTime($birthDate);
            $today = new \DateTime('today');
            $age = $today->diff($birth)->y;
            return $age >= $this->minimum_age;
        } catch(\Exception $e) {
            return false;
        }
    }

    protected function getAssets() {
        $assets = '';
        
        if($this->load_external_css) {
            $assets .= '<script src="https://cdn.tailwindcss.com"></script>';
            $assets .= '<script>' . $this->getTailwindConfig() . '</script>';
        }
        
        $css = $this->getCss();
        $js = $this->getJavaScript();
        
        $assets .= "\n<style>{$css}</style>\n<script>{$js}</script>\n";
        
        return $assets;
    }

    protected function getTailwindConfig() {
        return "
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'zoom-in': 'zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)',
                        'bounce-in': 'bounceIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(100px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        zoomIn: {
                            '0%': { transform: 'scale(0.7)', opacity: '0' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        },
                        bounceIn: {
                            '0%': { transform: 'scale(0.3)', opacity: '0' },
                            '50%': { transform: 'scale(1.05)' },
                            '70%': { transform: 'scale(0.9)' },
                            '100%': { transform: 'scale(1)', opacity: '1' }
                        }
                    }
                }
            }
        }
        ";
    }

    protected function getCss() {
        return "
        {$this->custom_css}
        .date-input-wrapper input::-webkit-outer-spin-button,
        .date-input-wrapper input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .date-input-wrapper input[type=number] {
            -moz-appearance: textfield;
        }
        ";
    }

    protected function getJavaScript() {
        $dateFormat = $this->wire('sanitizer')->entities($this->date_format);
        
        return "
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Age verification script loaded');
            const dateFormat = '{$dateFormat}';
            
            const overlay = document.getElementById('age-verification-overlay');
            if (!overlay) {
                console.error('Overlay not found');
                return;
            }
            
            const confirmBtn = document.getElementById('age-confirm-btn');
            const denyBtn = document.getElementById('age-deny-btn');
            const firstInput = document.getElementById('birth-first');
            const secondInput = document.getElementById('birth-second');
            const thirdInput = document.getElementById('birth-third');
            const errorDiv = document.getElementById('age-verification-error');
            
            // Auto-focus to next input
            if (firstInput && secondInput && thirdInput) {
                firstInput.addEventListener('input', function(e) {
                    const val = e.target.value;
                    const maxLen = firstInput.getAttribute('maxlength');
                    const maxVal = parseInt(firstInput.getAttribute('max'));
                    
                    if (val.length >= maxLen) {
                        const num = parseInt(val);
                        if (num > maxVal) {
                            e.target.value = maxVal.toString();
                        }
                        if (val.length === parseInt(maxLen)) {
                            secondInput.focus();
                        }
                    }
                });
                
                secondInput.addEventListener('input', function(e) {
                    const val = e.target.value;
                    const maxLen = secondInput.getAttribute('maxlength');
                    const maxVal = parseInt(secondInput.getAttribute('max'));
                    
                    if (val.length >= maxLen) {
                        const num = parseInt(val);
                        if (num > maxVal) {
                            e.target.value = maxVal.toString();
                        }
                        if (val.length === parseInt(maxLen)) {
                            thirdInput.focus();
                        }
                    }
                });
                
                secondInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value) {
                        firstInput.focus();
                    }
                });
                
                thirdInput.addEventListener('input', function(e) {
                    const maxLen = thirdInput.getAttribute('maxlength');
                    if (e.target.value.length > maxLen) {
                        e.target.value = e.target.value.slice(0, maxLen);
                    }
                });
                
                thirdInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value) {
                        secondInput.focus();
                    }
                });
            }
            
            console.log('Buttons found:', {confirmBtn, denyBtn});
            
            function handleVerification(confirmed) {
                console.log('handleVerification called with:', confirmed);
                
                const formData = new FormData();
                formData.append('age_verification_action', 'verify_age');
                formData.append('age_confirmed', confirmed);
                
                if (firstInput && secondInput && thirdInput) {
                    let year, month, day;
                    
                    if (dateFormat === 'mdy') {
                        month = firstInput.value.padStart(2, '0');
                        day = secondInput.value.padStart(2, '0');
                        year = thirdInput.value;
                    } else if (dateFormat === 'dmy') {
                        day = firstInput.value.padStart(2, '0');
                        month = secondInput.value.padStart(2, '0');
                        year = thirdInput.value;
                    } else if (dateFormat === 'ymd') {
                        year = firstInput.value;
                        month = secondInput.value.padStart(2, '0');
                        day = thirdInput.value.padStart(2, '0');
                    }
                    
                    if (month && day && year && year.length === 4) {
                        const birthDate = year + '-' + month + '-' + day;
                        formData.append('birth_date', birthDate);
                    }
                }
                
                if (confirmBtn) confirmBtn.disabled = true;
                if (denyBtn) denyBtn.disabled = true;
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(res => {
                    console.log('Response received:', res);
                    return res.json();
                })
                .then(data => {
                    console.log('Data:', data);
                    if (data.success) {
                        overlay.classList.add('opacity-0');
                        setTimeout(() => window.location.reload(), 300);
                    } else {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else if (errorDiv) {
                            errorDiv.textContent = data.message;
                            errorDiv.classList.remove('hidden');
                            if (confirmBtn) confirmBtn.disabled = false;
                            if (denyBtn) denyBtn.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorDiv) {
                        errorDiv.textContent = 'An error occurred. Please try again.';
                        errorDiv.classList.remove('hidden');
                        if (confirmBtn) confirmBtn.disabled = false;
                        if (denyBtn) denyBtn.disabled = false;
                    }
                });
            }
            
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function(e) {
                    console.log('Confirm button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    handleVerification('yes');
                });
            }
            
            if (denyBtn) {
                denyBtn.addEventListener('click', function(e) {
                    console.log('Deny button clicked');
                    e.preventDefault();
                    e.stopPropagation();
                    handleVerification('no');
                });
            }
            
            document.body.style.overflow = 'hidden';
        });
        ";
    }

    protected function getModalHtml() {
        $title = $this->wire('sanitizer')->entities($this->modal_title);
        $text = str_replace('{age}', $this->minimum_age, $this->wire('sanitizer')->entities($this->modal_text));
        $confirmText = str_replace('{age}', $this->minimum_age, $this->wire('sanitizer')->entities($this->confirm_button_text));
        $denyText = str_replace('{age}', $this->minimum_age, $this->wire('sanitizer')->entities($this->deny_button_text));
        
        $themeClasses = $this->getThemeClasses();
        $animationClass = $this->getAnimationClass();
        
        $html = "
        <div id='age-verification-overlay' class='fixed inset-0 z-[99999] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity duration-300 {$themeClasses['overlay']}'>
            <div class='relative w-full max-w-md {$animationClass}'>
                {$this->renderModalContent($themeClasses, $title, $text, $confirmText, $denyText)}
            </div>
        </div>
        ";
        
        return $html;
    }

    protected function renderModalContent($themeClasses, $title, $text, $confirmText, $denyText) {
        $datePickerHtml = $this->show_date_picker ? $this->getDatePickerHtml($themeClasses) : '';
        $buttonsHtml = $this->getButtonsHtml($themeClasses, $confirmText, $denyText);
        $agreementHtml = $this->show_agreement ? $this->getAgreementHtml($themeClasses) : '';
        
        return "
        <div class='{$themeClasses['container']}'>
            <h2 class='{$themeClasses['title']} text-center'>{$title}</h2>
            
            <p class='{$themeClasses['text']} text-center'>{$text}</p>
            
            {$datePickerHtml}
            
            {$buttonsHtml}
            
            {$agreementHtml}
            
            <div id='age-verification-error' class='hidden mt-4 p-3 {$themeClasses['error']} rounded text-sm animate-fade-in text-center'></div>
        </div>
        ";
    }

    protected function getAgreementHtml($themeClasses) {
        $agreementText = $this->wire('sanitizer')->entities($this->agreement_text);
        $privacyUrl = $this->wire('sanitizer')->entities($this->privacy_policy_url);
        $termsUrl = $this->wire('sanitizer')->entities($this->terms_of_use_url);
        
        return "
        <div class='mt-6 pt-4 border-t {$themeClasses['agreement_border']}'>
            <p class='text-xs text-center {$themeClasses['agreement_text']} mb-2'>
                {$agreementText}
            </p>
            <div class='flex items-center justify-center gap-3 text-xs'>
                <a href='{$privacyUrl}' target='_blank' rel='noopener noreferrer' class='{$themeClasses['agreement_link']} hover:underline font-medium'>
                    PRIVACY POLICY
                </a>
                <span class='{$themeClasses['agreement_separator']}'>|</span>
                <a href='{$termsUrl}' target='_blank' rel='noopener noreferrer' class='{$themeClasses['agreement_link']} hover:underline font-medium'>
                    TERMS OF USE
                </a>
            </div>
        </div>
        ";
    }

    protected function getThemeClasses() {
        $baseContainer = "p-8 border shadow-2xl rounded-lg";
        $baseTitle = "text-3xl font-bold mb-4";
        $baseText = "text-lg mb-6 leading-relaxed";
        
        switch($this->theme_style) {
            case 'dark':
                return array(
                    'overlay' => 'bg-black',
                    'container' => "{$baseContainer} bg-black border-zinc-800",
                    'title' => "{$baseTitle} text-white",
                    'text' => "{$baseText} text-zinc-300",
                    'button' => 'bg-zinc-700 hover:bg-zinc-600 text-white',
                    'input' => 'px-4 py-3 bg-zinc-900 border-2 border-zinc-700 text-white text-center text-xl font-semibold rounded focus:ring-2 focus:ring-zinc-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-zinc-400 mb-2',
                    'separator' => 'text-zinc-500 text-2xl font-bold',
                    'error' => 'bg-red-950 border border-red-800 text-red-300',
                    'agreement_border' => 'border-zinc-800',
                    'agreement_text' => 'text-zinc-500',
                    'agreement_link' => 'text-zinc-400 hover:text-zinc-300',
                    'agreement_separator' => 'text-zinc-700'
                );
            
            case 'minimal':
                return array(
                    'overlay' => 'bg-gray-900/50',
                    'container' => "{$baseContainer} bg-white border-gray-200",
                    'title' => "text-2xl font-semibold text-gray-900 mb-3",
                    'text' => "text-base text-gray-600 mb-6",
                    'button' => 'bg-gray-900 hover:bg-gray-800 text-white',
                    'input' => 'px-4 py-3 bg-white border-2 border-gray-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-gray-700 mb-2',
                    'separator' => 'text-gray-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-200 text-red-700',
                    'agreement_border' => 'border-gray-200',
                    'agreement_text' => 'text-gray-500',
                    'agreement_link' => 'text-gray-700 hover:text-gray-900',
                    'agreement_separator' => 'text-gray-300'
                );
            
            case 'classic':
                return array(
                    'overlay' => 'bg-black/70',
                    'container' => "{$baseContainer} bg-white border-gray-300",
                    'title' => "{$baseTitle} text-gray-900",
                    'text' => "{$baseText} text-gray-700",
                    'button' => 'bg-blue-700 hover:bg-blue-800 text-white',
                    'input' => 'px-4 py-3 bg-gray-50 border-2 border-gray-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-blue-700 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-gray-700 mb-2',
                    'separator' => 'text-gray-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-200 text-red-700',
                    'agreement_border' => 'border-gray-200',
                    'agreement_text' => 'text-gray-500',
                    'agreement_link' => 'text-blue-700 hover:text-blue-800',
                    'agreement_separator' => 'text-gray-300'
                );
            
            case 'gradient':
                return array(
                    'overlay' => 'bg-gradient-to-br from-purple-900/90 via-blue-900/90 to-pink-900/90',
                    'container' => "{$baseContainer} bg-white/95 backdrop-blur-xl border-white/20",
                    'title' => "{$baseTitle} bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent",
                    'text' => "{$baseText} text-gray-700",
                    'button' => 'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white',
                    'input' => 'px-4 py-3 bg-white/80 border-2 border-purple-200 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-gray-700 mb-2',
                    'separator' => 'text-purple-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-200 text-red-700',
                    'agreement_border' => 'border-purple-200',
                    'agreement_text' => 'text-gray-500',
                    'agreement_link' => 'text-purple-600 hover:text-pink-600',
                    'agreement_separator' => 'text-purple-200'
                );
            
            case 'neon':
                return array(
                    'overlay' => 'bg-black/95',
                    'container' => "{$baseContainer} bg-zinc-900 border-cyan-500 shadow-[0_0_30px_rgba(6,182,212,0.3)]",
                    'title' => "{$baseTitle} text-cyan-400 drop-shadow-[0_0_10px_rgba(6,182,212,0.8)]",
                    'text' => "{$baseText} text-cyan-100",
                    'button' => 'bg-cyan-500 hover:bg-cyan-400 text-black font-bold shadow-[0_0_20px_rgba(6,182,212,0.5)] hover:shadow-[0_0_30px_rgba(6,182,212,0.8)]',
                    'input' => 'px-4 py-3 bg-zinc-800 border-2 border-cyan-500 text-cyan-100 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-cyan-400 focus:border-transparent transition-all shadow-[0_0_10px_rgba(6,182,212,0.2)]',
                    'label' => 'block text-sm font-medium text-cyan-400 mb-2',
                    'separator' => 'text-cyan-500 text-2xl font-bold drop-shadow-[0_0_5px_rgba(6,182,212,0.5)]',
                    'error' => 'bg-red-950 border border-red-500 text-red-300 shadow-[0_0_10px_rgba(239,68,68,0.3)]',
                    'agreement_border' => 'border-cyan-900',
                    'agreement_text' => 'text-cyan-600',
                    'agreement_link' => 'text-cyan-400 hover:text-cyan-300',
                    'agreement_separator' => 'text-cyan-800'
                );
            
            case 'elegant':
                return array(
                    'overlay' => 'bg-slate-900/90',
                    'container' => "{$baseContainer} bg-gradient-to-br from-slate-50 to-slate-100 border-slate-200",
                    'title' => "{$baseTitle} text-slate-800 font-serif",
                    'text' => "{$baseText} text-slate-600 font-serif",
                    'button' => 'bg-slate-800 hover:bg-slate-700 text-slate-50',
                    'input' => 'px-4 py-3 bg-white border-2 border-slate-300 text-slate-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-slate-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-slate-600 mb-2 font-serif',
                    'separator' => 'text-slate-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-300 text-red-800',
                    'agreement_border' => 'border-slate-200',
                    'agreement_text' => 'text-slate-500 font-serif',
                    'agreement_link' => 'text-slate-700 hover:text-slate-900 font-serif',
                    'agreement_separator' => 'text-slate-300'
                );
            
            case 'corporate':
                return array(
                    'overlay' => 'bg-slate-800/90',
                    'container' => "{$baseContainer} bg-white border-slate-300",
                    'title' => "{$baseTitle} text-slate-900",
                    'text' => "{$baseText} text-slate-700",
                    'button' => 'bg-indigo-600 hover:bg-indigo-700 text-white',
                    'input' => 'px-4 py-3 bg-slate-50 border-2 border-slate-300 text-slate-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-slate-700 mb-2',
                    'separator' => 'text-slate-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-200 text-red-700',
                    'agreement_border' => 'border-slate-200',
                    'agreement_text' => 'text-slate-500',
                    'agreement_link' => 'text-indigo-600 hover:text-indigo-700',
                    'agreement_separator' => 'text-slate-300'
                );
            
            case 'vibrant':
                return array(
                    'overlay' => 'bg-gradient-to-br from-orange-500/80 via-pink-500/80 to-purple-600/80',
                    'container' => "{$baseContainer} bg-white border-white/50 shadow-2xl",
                    'title' => "{$baseTitle} text-transparent bg-clip-text bg-gradient-to-r from-orange-600 to-pink-600",
                    'text' => "{$baseText} text-gray-800",
                    'button' => 'bg-gradient-to-r from-orange-500 to-pink-500 hover:from-orange-600 hover:to-pink-600 text-white',
                    'input' => 'px-4 py-3 bg-orange-50 border-2 border-orange-300 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-pink-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-gray-700 mb-2',
                    'separator' => 'text-orange-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-300 text-red-800',
                    'agreement_border' => 'border-orange-200',
                    'agreement_text' => 'text-gray-500',
                    'agreement_link' => 'text-orange-600 hover:text-pink-600',
                    'agreement_separator' => 'text-orange-200'
                );
            
            case 'nature':
                return array(
                    'overlay' => 'bg-gradient-to-br from-emerald-900/90 via-green-800/90 to-teal-900/90',
                    'container' => "{$baseContainer} bg-white border-green-200",
                    'title' => "{$baseTitle} text-green-800",
                    'text' => "{$baseText} text-green-900",
                    'button' => 'bg-green-600 hover:bg-green-700 text-white',
                    'input' => 'px-4 py-3 bg-green-50 border-2 border-green-300 text-green-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-green-700 mb-2',
                    'separator' => 'text-green-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-300 text-red-800',
                    'agreement_border' => 'border-green-200',
                    'agreement_text' => 'text-green-600',
                    'agreement_link' => 'text-green-700 hover:text-green-800',
                    'agreement_separator' => 'text-green-200'
                );
            
            case 'sunset':
                return array(
                    'overlay' => 'bg-gradient-to-br from-orange-600/85 via-red-500/85 to-pink-600/85',
                    'container' => "{$baseContainer} bg-white/95 backdrop-blur-sm border-orange-200",
                    'title' => "{$baseTitle} text-orange-800",
                    'text' => "{$baseText} text-orange-900",
                    'button' => 'bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white',
                    'input' => 'px-4 py-3 bg-orange-50 border-2 border-orange-300 text-orange-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-orange-700 mb-2',
                    'separator' => 'text-orange-400 text-2xl font-bold',
                    'error' => 'bg-red-100 border border-red-300 text-red-800',
                    'agreement_border' => 'border-orange-200',
                    'agreement_text' => 'text-orange-600',
                    'agreement_link' => 'text-orange-700 hover:text-red-600',
                    'agreement_separator' => 'text-orange-200'
                );
            
            case 'ocean':
                return array(
                    'overlay' => 'bg-gradient-to-br from-blue-900/90 via-cyan-800/90 to-teal-900/90',
                    'container' => "{$baseContainer} bg-white border-blue-200",
                    'title' => "{$baseTitle} text-blue-900",
                    'text' => "{$baseText} text-cyan-900",
                    'button' => 'bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white',
                    'input' => 'px-4 py-3 bg-blue-50 border-2 border-blue-300 text-blue-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-blue-700 mb-2',
                    'separator' => 'text-blue-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-300 text-red-800',
                    'agreement_border' => 'border-blue-200',
                    'agreement_text' => 'text-cyan-600',
                    'agreement_link' => 'text-blue-700 hover:text-cyan-700',
                    'agreement_separator' => 'text-blue-200'
                );
            
            case 'purple':
                return array(
                    'overlay' => 'bg-gradient-to-br from-purple-900/90 via-violet-800/90 to-indigo-900/90',
                    'container' => "{$baseContainer} bg-white border-purple-200",
                    'title' => "{$baseTitle} text-purple-900",
                    'text' => "{$baseText} text-purple-800",
                    'button' => 'bg-purple-600 hover:bg-purple-700 text-white',
                    'input' => 'px-4 py-3 bg-purple-50 border-2 border-purple-300 text-purple-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-purple-700 mb-2',
                    'separator' => 'text-purple-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-300 text-red-800',
                    'agreement_border' => 'border-purple-200',
                    'agreement_text' => 'text-purple-600',
                    'agreement_link' => 'text-purple-700 hover:text-purple-800',
                    'agreement_separator' => 'text-purple-200'
                );
            
            default: // modern
                return array(
                    'overlay' => 'bg-slate-900/80',
                    'container' => "{$baseContainer} bg-white border-gray-100",
                    'title' => "{$baseTitle} text-gray-900",
                    'text' => "{$baseText} text-gray-700",
                    'button' => 'bg-blue-600 hover:bg-blue-700 text-white',
                    'input' => 'px-4 py-3 bg-gray-50 border-2 border-gray-200 text-gray-900 text-center text-xl font-semibold rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all',
                    'label' => 'block text-sm font-medium text-gray-600 mb-2',
                    'separator' => 'text-gray-400 text-2xl font-bold',
                    'error' => 'bg-red-50 border border-red-200 text-red-700',
                    'agreement_border' => 'border-gray-200',
                    'agreement_text' => 'text-gray-500',
                    'agreement_link' => 'text-blue-600 hover:text-blue-700',
                    'agreement_separator' => 'text-gray-300'
                );
        }
    }

    protected function getAnimationClass() {
        $animations = array(
            'fade' => 'animate-fade-in',
            'slide' => 'animate-slide-up',
            'zoom' => 'animate-zoom-in',
            'bounce' => 'animate-bounce-in'
        );
        return $animations[$this->animation_style] ?? 'animate-fade-in';
    }

    protected function getDatePickerHtml($themeClasses) {
        $datePickerText = $this->wire('sanitizer')->entities($this->date_picker_text);
        $format = $this->date_format;
        
        // Define field configurations based on format
        $fields = array();
        
        switch($format) {
            case 'mdy': // MM/DD/YYYY (American)
                $fields = array(
                    array('id' => 'birth-first', 'label' => 'MM', 'placeholder' => 'MM', 'min' => '1', 'max' => '12', 'maxlength' => '2'),
                    array('id' => 'birth-second', 'label' => 'DD', 'placeholder' => 'DD', 'min' => '1', 'max' => '31', 'maxlength' => '2'),
                    array('id' => 'birth-third', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4')
                );
                break;
                
            case 'dmy': // DD/MM/YYYY (European)
                $fields = array(
                    array('id' => 'birth-first', 'label' => 'DD', 'placeholder' => 'DD', 'min' => '1', 'max' => '31', 'maxlength' => '2'),
                    array('id' => 'birth-second', 'label' => 'MM', 'placeholder' => 'MM', 'min' => '1', 'max' => '12', 'maxlength' => '2'),
                    array('id' => 'birth-third', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4')
                );
                break;
                
            case 'ymd': // YYYY/MM/DD (ISO)
                $fields = array(
                    array('id' => 'birth-first', 'label' => 'YYYY', 'placeholder' => 'YYYY', 'min' => '1900', 'max' => '2100', 'maxlength' => '4'),
                    array('id' => 'birth-second', 'label' => 'MM', 'placeholder' => 'MM', 'min' => '1', 'max' => '12', 'maxlength' => '2'),
                    array('id' => 'birth-third', 'label' => 'DD', 'placeholder' => 'DD', 'min' => '1', 'max' => '31', 'maxlength' => '2')
                );
                break;
        }
        
        $html = "
        <div class='mb-6 date-input-wrapper'>
            <label class='text-center {$themeClasses['label']}'>{$datePickerText}</label>
            <div class='flex items-center justify-center gap-2 mt-3 max-w-xs mx-auto'>
                <div class='" . ($fields[0]['maxlength'] == '4' ? 'flex-[1.5]' : 'flex-1') . "'>
                    <label class='block text-xs text-center mb-1 {$themeClasses['label']}'>{$fields[0]['label']}</label>
                    <input 
                        type='number' 
                        id='{$fields[0]['id']}' 
                        class='{$themeClasses['input']}' 
                        placeholder='{$fields[0]['placeholder']}'
                        min='{$fields[0]['min']}'
                        max='{$fields[0]['max']}'
                        maxlength='{$fields[0]['maxlength']}'
                        required
                    >
                </div>
                <span class='mt-6 {$themeClasses['separator']}'>/</span>
                <div class='flex-1'>
                    <label class='block text-xs text-center mb-1 {$themeClasses['label']}'>{$fields[1]['label']}</label>
                    <input 
                        type='number' 
                        id='{$fields[1]['id']}' 
                        class='{$themeClasses['input']}' 
                        placeholder='{$fields[1]['placeholder']}'
                        min='{$fields[1]['min']}'
                        max='{$fields[1]['max']}'
                        maxlength='{$fields[1]['maxlength']}'
                        required
                    >
                </div>
                <span class='mt-6 {$themeClasses['separator']}'>/</span>
                <div class='" . ($fields[2]['maxlength'] == '4' ? 'flex-[1.5]' : 'flex-1') . "'>
                    <label class='block text-xs text-center mb-1 {$themeClasses['label']}'>{$fields[2]['label']}</label>
                    <input 
                        type='number' 
                        id='{$fields[2]['id']}' 
                        class='{$themeClasses['input']}' 
                        placeholder='{$fields[2]['placeholder']}'
                        min='{$fields[2]['min']}'
                        max='{$fields[2]['max']}'
                        maxlength='{$fields[2]['maxlength']}'
                        required
                    >
                </div>
            </div>
        </div>
        ";
        
        return $html;
    }

    protected function getButtonsHtml($themeClasses, $confirmText, $denyText) {
        $buttonClasses = "font-semibold text-base py-3 px-6 rounded transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed";
        
        if($this->show_date_picker) {
            return "
            <button type='button' id='age-confirm-btn' class='w-full {$buttonClasses} {$themeClasses['button']}'>
                {$confirmText}
            </button>
            ";
        }
        
        return "
        <div class='flex flex-col sm:flex-row gap-3'>
            <button type='button' id='age-confirm-btn' class='flex-1 {$buttonClasses} {$themeClasses['button']}'>
                {$confirmText}
            </button>
            <button type='button' id='age-deny-btn' class='flex-1 bg-gray-500 hover:bg-gray-600 text-white {$buttonClasses}'>
                {$denyText}
            </button>
        </div>
        ";
    }

    public static function getModuleConfigInputfields(array $data) {
        $data = array_merge(self::$defaultConfig, $data);
        $inputfields = new InputfieldWrapper();

        // General Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'General Settings';
        $fieldset->collapsed = false;

        $field = wire('modules')->get('InputfieldCheckbox');
        $field->name = 'enabled';
        $field->label = 'Enable Age Verification';
        $field->description = 'Check to enable age verification on your website.';
        $field->checked = $data['enabled'] ? 'checked' : '';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldInteger');
        $field->name = 'minimum_age';
        $field->label = 'Minimum Age';
        $field->description = 'Minimum age required to access the website content.';
        $field->value = $data['minimum_age'];
        $field->min = 1;
        $field->max = 100;
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'cookie_name';
        $field->label = 'Cookie Name';
        $field->description = 'Name of the cookie used to store age verification status.';
        $field->value = $data['cookie_name'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldInteger');
        $field->name = 'cookie_lifetime';
        $field->label = 'Cookie Lifetime (seconds)';
        $field->description = 'How long the age verification cookie should last (default: 2592000 = 30 days).

**Common values:**
- 1 day = 86400
- 7 days = 604800
- 14 days = 1209600
- 30 days (1 month) = 2592000
- 90 days (3 months) = 7776000
- 180 days (6 months) = 15552000';
        $field->value = $data['cookie_lifetime'];
        $field->min = 60;
        $field->notes = 'Recommended: 2592000 (30 days) for balance between security and user experience.';
        $fieldset->add($field);

        $inputfields->add($fieldset);

        // Content Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'Content Settings';
        $fieldset->collapsed = true;

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'modal_title';
        $field->label = 'Modal Title';
        $field->value = $data['modal_title'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldTextarea');
        $field->name = 'modal_text';
        $field->label = 'Modal Text';
        $field->description = 'Text displayed in the age verification modal. Use {age} placeholder for minimum age.';
        $field->value = $data['modal_text'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'confirm_button_text';
        $field->label = 'Confirm Button Text';
        $field->description = 'Text for the confirmation button. Use {age} placeholder for minimum age.';
        $field->value = $data['confirm_button_text'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'deny_button_text';
        $field->label = 'Deny Button Text';
        $field->description = 'Text for the denial button. Use {age} placeholder for minimum age.';
        $field->value = $data['deny_button_text'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldURL');
        $field->name = 'redirect_url';
        $field->label = 'Redirect URL';
        $field->description = 'URL to redirect users who are under the minimum age.';
        $field->value = $data['redirect_url'];
        $fieldset->add($field);

        $inputfields->add($fieldset);

        // Date Picker Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'Date Picker Settings';
        $fieldset->collapsed = true;

        $field = wire('modules')->get('InputfieldCheckbox');
        $field->name = 'show_date_picker';
        $field->label = 'Show Date Picker';
        $field->description = 'Enable to show separate date inputs for age verification instead of simple yes/no buttons. This provides better bot protection.';
        $field->checked = $data['show_date_picker'] ? 'checked' : '';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldSelect');
        $field->name = 'date_format';
        $field->label = 'Date Format';
        $field->description = 'Choose the date format for the date picker.';
        $field->addOption('mdy', 'MM/DD/YYYY (American format)');
        $field->addOption('dmy', 'DD/MM/YYYY (European format)');
        $field->addOption('ymd', 'YYYY/MM/DD (ISO format)');
        $field->value = $data['date_format'];
        $field->showIf = 'show_date_picker=1';
        $field->notes = 'Select the date format that matches your region or preference.';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'date_picker_text';
        $field->label = 'Date Picker Text';
        $field->description = 'Text displayed above the date picker.';
        $field->value = $data['date_picker_text'];
        $field->showIf = 'show_date_picker=1';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'invalid_date_text';
        $field->label = 'Invalid Date Text';
        $field->description = 'Message shown when an invalid date of birth is entered.';
        $field->value = $data['invalid_date_text'];
        $field->showIf = 'show_date_picker=1';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldText');
        $field->name = 'underage_text';
        $field->label = 'Underage Text';
        $field->description = 'Message shown to users who are under the minimum age. Use {age} placeholder.';
        $field->value = $data['underage_text'];
        $field->showIf = 'show_date_picker=1';
        $fieldset->add($field);

        $inputfields->add($fieldset);

        // Agreement Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'Terms & Privacy Agreement';
        $fieldset->collapsed = true;

        $field = wire('modules')->get('InputfieldCheckbox');
        $field->name = 'show_agreement';
        $field->label = 'Show Agreement Text';
        $field->description = 'Display terms of use and privacy policy agreement at the bottom of the modal.';
        $field->checked = $data['show_agreement'] ? 'checked' : '';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldTextarea');
        $field->name = 'agreement_text';
        $field->label = 'Agreement Text';
        $field->description = 'Text shown above the policy links.';
        $field->value = $data['agreement_text'];
        $field->showIf = 'show_agreement=1';
        $field->rows = 2;
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldURL');
        $field->name = 'privacy_policy_url';
        $field->label = 'Privacy Policy URL';
        $field->description = 'Link to your privacy policy page.';
        $field->value = $data['privacy_policy_url'];
        $field->showIf = 'show_agreement=1';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldURL');
        $field->name = 'terms_of_use_url';
        $field->label = 'Terms of Use URL';
        $field->description = 'Link to your terms of use page.';
        $field->value = $data['terms_of_use_url'];
        $field->showIf = 'show_agreement=1';
        $fieldset->add($field);

        $inputfields->add($fieldset);

        // Exclusion Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'Exclusion Settings';
        $fieldset->collapsed = true;

        $field = wire('modules')->get('InputfieldAsmSelect');
        $field->name = 'excluded_templates';
        $field->label = 'Excluded Templates';
        $field->description = 'Select templates where age verification should not be shown.';
        foreach(wire('templates') as $template) {
            if($template->name !== 'admin') {
                $field->addOption($template->name, $template->name);
            }
        }
        $field->value = $data['excluded_templates'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldPageListSelectMultiple');
        $field->name = 'excluded_pages';
        $field->label = 'Excluded Pages';
        $field->description = 'Select specific pages where age verification should not be shown.';
        $field->value = $data['excluded_pages'];
        $fieldset->add($field);

        $inputfields->add($fieldset);

        // Framework and Theme Settings
        $fieldset = wire('modules')->get('InputfieldFieldset');
        $fieldset->label = 'Framework and Theme Settings';
        $fieldset->collapsed = true;

        $field = wire('modules')->get('InputfieldSelect');
        $field->name = 'theme_style';
        $field->label = 'Theme Style';
        $field->description = 'Choose the visual theme for the modal.';
        $field->addOption('modern', 'Modern - Clean blue design');
        $field->addOption('dark', 'Dark - Pure black with zinc accents');
        $field->addOption('classic', 'Classic - Traditional blue style');
        $field->addOption('minimal', 'Minimal - Simple monochrome');
        $field->addOption('gradient', 'Gradient - Purple to pink gradient');
        $field->addOption('neon', 'Neon - Cyberpunk cyan glow');
        $field->addOption('elegant', 'Elegant - Sophisticated slate tones');
        $field->addOption('corporate', 'Corporate - Professional indigo');
        $field->addOption('vibrant', 'Vibrant - Orange and pink energy');
        $field->addOption('nature', 'Nature - Fresh green tones');
        $field->addOption('sunset', 'Sunset - Warm orange to red');
        $field->addOption('ocean', 'Ocean - Cool blue to cyan');
        $field->addOption('purple', 'Purple - Rich purple theme');
        $field->value = $data['theme_style'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldSelect');
        $field->name = 'animation_style';
        $field->label = 'Animation Style';
        $field->description = 'Choose the animation effect for the modal appearance.';
        $field->addOption('fade', 'Fade In');
        $field->addOption('slide', 'Slide Up');
        $field->addOption('zoom', 'Zoom In');
        $field->addOption('bounce', 'Bounce In');
        $field->value = $data['animation_style'];
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldCheckbox');
        $field->name = 'load_external_css';
        $field->label = 'Load External CSS';
        $field->description = 'Enable to load Tailwind CSS from CDN (recommended).';
        $field->checked = $data['load_external_css'] ? 'checked' : '';
        $fieldset->add($field);

        $field = wire('modules')->get('InputfieldTextarea');
        $field->name = 'custom_css';
        $field->label = 'Custom CSS';
        $field->description = 'Add custom CSS styles to override or extend the default styling.';
        $field->value = $data['custom_css'];
        $field->rows = 10;
        $fieldset->add($field);

        $inputfields->add($fieldset);

        return $inputfields;
    }
}