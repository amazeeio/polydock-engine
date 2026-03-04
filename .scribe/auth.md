# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Authentication is handled via Sanctum Bearer tokens. You can retrieve your token by authenticating or visiting your dashboard to generate an API token. Include it in your requests as an `Authorization: Bearer {YOUR_AUTH_TOKEN}` header.
