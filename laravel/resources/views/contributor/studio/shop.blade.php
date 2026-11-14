@extends('layouts.studio')
@section('title', 'Shop Details')

@php
    $c = $contributor;
    $s = (array) ($c->social_links ?? []);
    $avatar = $c->avatar ? Storage::disk('public')->url($c->avatar) : null;
    $countries = ['PK' => 'Pakistan', 'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'TR' => 'Turkey', 'BD' => 'Bangladesh', 'ID' => 'Indonesia', 'MY' => 'Malaysia', 'PH' => 'Philippines', 'BR' => 'Brazil', 'MX' => 'Mexico', 'NG' => 'Nigeria', 'EG' => 'Egypt', 'ZA' => 'South Africa', 'JP' => 'Japan', 'SG' => 'Singapore', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'SE' => 'Sweden', 'PL' => 'Poland', 'UA' => 'Ukraine'];
@endphp

@section('content')
<form method="POST" action="{{ route('contributor.shop.update') }}" enctype="multipart/form-data" class="cs-form">
    @csrf
    <label class="cs-avatar-big" for="csAvatar" title="Change photo">
        @if ($avatar)<img src="{{ $avatar }}" alt="" data-cs-avatar-preview>@else<span data-cs-avatar-initial>{{ Str::upper(Str::substr($c->display_name, 0, 1)) }}</span>@endif
    </label>
    <input id="csAvatar" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" hidden onchange="if(this.files[0]){var l=this.previousElementSibling;l.innerHTML='<img src=&quot;'+URL.createObjectURL(this.files[0])+'&quot; alt=&quot;&quot;>';}">

    <input class="cs-input" name="display_name" value="{{ old('display_name', $c->display_name) }}" maxlength="120" required aria-label="Store or company name" placeholder="Store / company name">
    <p class="cs-form__help">If you enter your store/company name, it will be visible in your profile instead of your first &amp; last name.</p>

    <input class="cs-input" value="{{ $c->slug }}" disabled aria-label="Username">
    <p class="cs-form__help">Your Iconors URL will be: <a href="{{ route('contributors.show', $c->slug) }}" target="_blank" rel="noopener">{{ route('contributors.show', $c->slug) }}</a>. You will not be able to change your username in future.</p>

    <textarea class="cs-input" name="biography" rows="2" maxlength="300" placeholder="Motion Designer | UI Designer | Graphic Designer" aria-label="Headline">{{ old('biography', $c->biography) }}</textarea>

    <select class="cs-select" name="country" aria-label="Country">
        <option value="">Country</option>
        @foreach ($countries as $code => $name)<option value="{{ $code }}" @selected(old('country', $c->country) === $code)>{{ $name }}</option>@endforeach
    </select>

    <input class="cs-input" type="url" name="website" value="{{ old('website', $c->website) }}" placeholder="Website" aria-label="Website">
    <div class="cs-form__two">
        @foreach (['dribbble' => 'Dribbble', 'facebook' => 'Facebook', 'behance' => 'Behance', 'twitter' => 'Twitter / X', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn'] as $k => $label)
            <input class="cs-input" type="url" name="social[{{ $k }}]" value="{{ old('social.'.$k, $s[$k] ?? '') }}" placeholder="{{ $label }}" aria-label="{{ $label }}">
        @endforeach
    </div>

    <label for="csAi">AI Training Consent</label>
    <select id="csAi" class="cs-select" name="ai_training_consent">
        <option value="yes" @selected(($s['ai_training_consent'] ?? 'no') === 'yes')>Yes</option>
        <option value="no" @selected(($s['ai_training_consent'] ?? 'no') === 'no')>No</option>
    </select>

    <button class="cs-btn cs-btn--primary mt-2" type="submit">Save</button>
</form>
@endsection
