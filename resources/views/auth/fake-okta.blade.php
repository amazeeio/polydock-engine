<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fake Okta (local dev)</title>
    <style>
        body { font-family: sans-serif; max-width: 26rem; margin: 4rem auto; padding: 0 1rem; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input[type=text], input[type=email] { width: 100%; padding: .5rem; margin-top: .25rem; }
        fieldset { margin-top: 1rem; }
        button { margin-top: 1.5rem; padding: .6rem 1.2rem; font-size: 1rem; }
        .warn { background: #fef3c7; border: 1px solid #f59e0b; padding: .5rem 1rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <h1>Fake Okta</h1>
    <p class="warn">Local development only — this pretends to be the company Okta.</p>

    <form method="get" action="{{ route('okta.callback') }}">
        <input type="hidden" name="state" value="{{ $state }}">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="staff@amazee.io" required>

        <label for="given_name">Given name</label>
        <input type="text" id="given_name" name="given_name" value="Fake">

        <label for="family_name">Family name</label>
        <input type="text" id="family_name" name="family_name" value="Staffer">

        <fieldset>
            <legend>Okta groups (drive role sync)</legend>
            <label><input type="checkbox" name="groups[]" value="polydock-admins"> polydock-admins → super_admin</label>
            <label><input type="checkbox" name="groups[]" value="polydock-support"> polydock-support → support</label>
        </fieldset>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
