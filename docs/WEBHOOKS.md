# Outbound webhooks

Polydock delivers events to a store's configured webhook URL by POSTing a JSON
body. Each `PolydockStoreWebhook` has an auto-generated `secret` (created with
the webhook and never exposed in API/JSON responses) that is used to sign every
outbound request.

## Request shape

Each delivery is an HTTP `POST` with a JSON body and the following headers:

| Header | Description |
|---|---|
| `Content-Type` | `application/json` |
| `User-Agent` | `PolydockWebhook/1.0` |
| `X-Polydock-Event` | The event name (e.g. `app.created`) |
| `X-Polydock-Delivery` | The unique webhook-call id |
| `X-Polydock-Attempt` | The current delivery attempt number |
| `X-Polydock-Signature` | `sha256=<hex>` HMAC of the raw request body |

## Verifying the signature

The `X-Polydock-Signature` header lets you confirm a request genuinely came
from Polydock. It is the HMAC-SHA256 of the **raw request body** keyed with your
webhook's `secret`, formatted as `sha256=<hex digest>`.

The signed bytes are exactly the transmitted bytes, so compute the HMAC over the
raw body **before** any JSON parsing or re-serialization.

To verify:

1. Read the raw request body (do not re-encode it).
2. Compute `hash_hmac('sha256', $rawBody, $secret)`.
3. Prefix it with `sha256=` and compare, in constant time, to the
   `X-Polydock-Signature` header value.

### PHP example

```php
$rawBody = file_get_contents('php://input');
$expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
$provided = $_SERVER['HTTP_X_POLYDOCK_SIGNATURE'] ?? '';

if (! hash_equals($expected, $provided)) {
    http_response_code(401);
    exit;
}
```

Always use a constant-time comparison (`hash_equals` in PHP, `crypto.timingSafeEqual`
in Node, `hmac.compare_digest` in Python) to avoid timing attacks. Reject any
request whose signature does not match.
