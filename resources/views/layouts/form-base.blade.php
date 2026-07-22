{{--
    Base scaffold for hosted /f/{slug} forms.

    A concrete form view extends this and provides:
      - @section('fields')      — required: the form inputs (see forms/partials for shared field groups)
      - @section('intro')       — optional: heading/description shown above the form
      - @section('form-styles') — optional: form-specific <style> additions
      - @section('submit-label')— optional: submit button label (defaults to "Sign Up Now")
--}}
@extends('layouts.form-iframe')

@section('title', $form->getSeoTitle())
@section('seo_description', $form->getSeoDescription())

@section('styles')
@include('forms.partials.base-styles')
@yield('form-styles')
@if($form->getRecaptchaEnabled())
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
@endsection

@section('content')
<div class="form-container">
    @yield('intro')

    <!-- Server/Client Error Container -->
    <div id="errorBox" class="error-container"></div>

    <!-- MAIN REGISTRATION FORM -->
    <form id="signupForm" class="space-y-4">
        @csrf

        @yield('fields')

        @if($form->getRecaptchaEnabled())
        <!-- Google reCAPTCHA Block -->
        <div class="recaptcha-container">
            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}"></div>
        </div>
        @endif

        <button type="submit" id="btnSubmit" class="btn-submit">@yield('submit-label', 'Sign Up Now')</button>
    </form>

    @include('forms.partials.status-screens')
</div>
@endsection

@section('scripts')
@include('forms.partials.form-scripts')
@endsection
