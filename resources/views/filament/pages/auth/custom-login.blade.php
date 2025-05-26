{{-- resources/views/filament/pages/auth/custom-login.blade.php --}}

<x-filament-panels::page.simple>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-950">
    {{-- Top Company Name --}}
    <div class="text-gray-200 mb-4">
      Gasgo
    </div>

    {{-- Welcome Text --}}
    <div class="text-center mb-4">
      <h2 class="text-2xl font-bold text-white">
        Welcome Back!
      </h2>
      <p class="text-gray-400 text-sm mt-1">
        Please sign in to access your dashboard
      </p>
    </div>

    {{-- Main Card --}}
    <div class="w-full max-w-sm bg-gray-900 rounded-lg p-8">
      {{-- Logo and Title --}}
      <div class="text-center mb-6">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-12 mx-auto mb-4">
        <h1 class="text-xl font-bold text-white">
          Exam Mastery Hub
        </h1>
        <p class="text-sm text-gray-400">
          Unleash Your Academic Success
        </p>
      </div>

      {{-- Login Form --}}
      <x-filament-panels::form wire:submit="authenticate">
        <div class="space-y-4">
          {{-- Form Fields with Dark Styling --}}
          <div class="space-y-4">
            {{ $this->form }}
          </div>

          {{-- Forgot Password --}}
          <div class="flex justify-end">
            <a href="#" class="text-sm text-gray-400 hover:text-orange-500">
              Forgot password?
            </a>
          </div>

          {{-- Submit Button --}}
          <x-filament::button
            type="submit"
            class="w-full bg-orange-500 hover:bg-orange-600 focus:ring-orange-500 py-2"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-70 cursor-wait">
            <span wire:loading.remove wire:target="authenticate">
              Sign in to your account
            </span>
            <span wire:loading wire:target="authenticate" class="flex items-center justify-center">
              <x-filament::loading-indicator class="h-5 w-5 mr-2" />
              Signing in...
            </span>
          </x-filament::button>

          {{-- Divider --}}
          <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-gray-900 text-gray-400">
                or continue with
              </span>
            </div>
          </div>

          {{-- Google Sign In --}}
          <button type="button"
            class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg px-4 py-2 text-sm transition-colors duration-200">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
            </svg>
            Sign in with Google
          </button>

          {{-- Sign Up Link --}}
          <div class="mt-6 text-center text-sm text-gray-400">
            Don't have an account?
            <a href="#" class="text-orange-500 hover:text-orange-400 ml-1">
              Create account
            </a>
          </div>
        </div>
      </x-filament-panels::form>
    </div>
  </div>
</x-filament-panels::page.simple>