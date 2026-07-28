@extends('layouts.form-base')

@section('fields')
@include('forms.partials.contact-fields')

<div class="form-group">
    <label class="form-label" for="organization">Organization</label>
    <input type="text" id="organization" name="organization" class="form-control">
</div>

<div class="form-group">
    <label class="form-label" for="jobTitle">Job Title</label>
    <input type="text" id="jobTitle" name="job_title" class="form-control">
</div>

<div class="form-group">
    <label class="form-label" for="country">Country</label>
    <select id="country" name="country" class="form-control">
        <option value="" selected>Select</option>
        @foreach ($countries as $code => $name)
            <option value="{{ $name }}">{{ $name }}</option>
        @endforeach
    </select>
</div>

<div class="form-group">
    <label class="form-label" for="stageInAIAdoption">Stage in AI Adoption</label>
    <select id="stageInAIAdoption" name="stage_in_ai_adoption" class="form-control">
        <option value="" selected>Select</option>
        <option value="just-curious">Just curious</option>
        <option value="specific-need">I have a specific need</option>
        <option value="already-using">Already using AI</option>
    </select>
</div>

<div class="form-group">
    <label class="form-label" for="interestInDrupalAI">Interest in Drupal AI</label>
    <input type="text" id="interestInDrupalAI" name="interest_in_drupal_ai" class="form-control">
</div>

@include('forms.partials.region-app-fields')

<div class="disclaimer-text">
    <p>
        By participating in this trial, you acknowledge that you have read, understood, and
        agree to the <a href="https://amazee.ai/terms-and-conditions" target="_blank">terms of this consent</a>
        and you grant permission to the Drupal Association and <a href="https://amazee.ai" target="_blank">amazee.ai</a>
        to <a href="https://docs.amazee.io/policy/acceptable-use" target="_blank">share your personal information</a>
        for the purposes of facilitating your participation in the trial and the Purpose set forth therein.
    </p>
    <p>
        By participating in this trial, you acknowledge that you have read, understood, and
        agree to the privacy policies of both the <a href="https://www.drupal.org/privacy" target="_blank">Drupal Association</a>
        and our Trial Partner, <a href="https://amazee.ai" target="_blank">amazee.ai</a>.
    </p>
</div>
@endsection
