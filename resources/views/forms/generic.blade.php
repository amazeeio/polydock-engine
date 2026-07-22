@extends('layouts.form-base')

@section('form-styles')
<style>
    .form-heading {
        color: var(--color-primary);
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .form-description {
        color: var(--color-text-muted);
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 1rem;
        word-break: break-word;
    }

    .form-description a {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
    }

    .form-description a:hover {
        text-decoration: underline;
    }

    .notice-box {
        background-color: rgba(59, 130, 246, 0.06);
        border: 1px solid rgba(59, 130, 246, 0.25);
        color: #1d4ed8;
        font-size: 0.9rem;
        font-weight: 500;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .checkbox-group {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        margin: 1.5rem 0;
        font-size: 0.9rem;
        color: var(--color-text-muted);
    }

    .checkbox-group input[type="checkbox"] {
        margin-top: 0.2rem;
        width: 1.1rem;
        height: 1.1rem;
        flex-shrink: 0;
        accent-color: var(--color-primary);
    }

    .checkbox-group a {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 500;
    }

    .checkbox-group a:hover {
        text-decoration: underline;
    }
</style>
@endsection

{{-- Description, notice, and disclaimer are admin-authored HTML from the PolydockHostedForm record --}}
@section('intro')
<h1 class="form-heading">{{ $form->getTitle() }}</h1>
@if($form->getDescription())
<div class="form-description">{!! $form->getDescription() !!}</div>
@endif
@if($form->getNotice())
<div class="notice-box">{!! $form->getNotice() !!}</div>
@endif
@endsection

@section('fields')
@include('forms.partials.contact-fields')

<div class="form-group">
    <label class="form-label" for="organization">Company</label>
    <input type="text" id="organization" name="organization" class="form-control" autocomplete="organization">
</div>

@include('forms.partials.region-app-fields')

@if($form->getDisclaimer())
<div class="disclaimer-text">{!! $form->getDisclaimer() !!}</div>
@endif

<div class="checkbox-group">
    <input type="checkbox" id="acceptTerms" name="accept_terms" value="1" required>
    <label for="acceptTerms">
        I accept the <a href="https://amazee.ai/terms-and-conditions" target="_blank" rel="noopener">amazee.ai terms and conditions</a>
        and the <a href="https://docs.amazee.io/policy/acceptable-use" target="_blank" rel="noopener">acceptable use policy</a>. <span class="required-star">*</span>
    </label>
</div>
@endsection
