<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
    <head>
        <meta charset="UTF-8" />
        <title>Registration - {{ config('other.title') }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <link rel="shortcut icon" href="{{ url('/favicon.ico') }}" type="image/x-icon" />
        <link rel="icon" href="{{ url('/favicon.ico') }}" type="image/x-icon" />
        @vite('resources/sass/pages/_auth.scss')
    </head>
    <body>
        <main>
            <section class="auth-form">
                <form style="text-align: center;">
                    <a class="auth-form__branding" href="{{ route('home.index') }}">
                        <i class="fal fa-tv-retro"></i>
                        <span class="auth-form__site-logo">{{ config('other.title') }}</span>
                    </a>
                    
                    <h2 style="color: #f39c12; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle"></i> Registration Disabled
                    </h2>
                    
                    <div style="background: rgba(52, 73, 94, 0.8); border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <p style="color: #ecf0f1; font-size: 16px; line-height: 1.6; margin-bottom: 15px;">
                            <strong>Accounts are automatically created</strong> when you purchase a torrent product from the ArcadeSystems store.
                        </p>
                        
                        <p style="color: #bdc3c7; font-size: 14px; line-height: 1.6; margin-bottom: 15px;">
                            To access your RetroHo account:
                        </p>
                        
                        <ol style="color: #bdc3c7; font-size: 14px; text-align: left; padding-left: 25px; line-height: 1.8;">
                            <li>Visit <a href="https://portal.arcadesystems.co.uk" style="color: #3498db;">portal.arcadesystems.co.uk</a></li>
                            <li>Log in with your order email address</li>
                            <li>Go to the <strong>Torrents</strong> tab</li>
                            <li>Your account will be created automatically</li>
                            <li>Use the <strong>Auto-Login</strong> button to access RetroHo</li>
                        </ol>
                    </div>
                    
                    <p style="color: #95a5a6; font-size: 13px; margin-bottom: 20px;">
                        Already have an account? <a href="{{ route('login') }}" style="color: #3498db;">Login here</a>
                    </p>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <a href="https://portal.arcadesystems.co.uk" class="auth-form__primary-btn" style="background: #27ae60; padding: 12px 24px; border-radius: 5px; color: white; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> Go to Portal
                        </a>
                        <a href="{{ route('login') }}" class="auth-form__secondary-btn" style="background: #3498db; padding: 12px 24px; border-radius: 5px; color: white; text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </div>
                </form>
            </section>
        </main>
    </body>
</html>
