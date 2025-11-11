# Polydock Engine API Documentation

This document describes the REST API endpoints available in Polydock Engine.

## Base URL

All API endpoints are prefixed with `/api`. For example:
- Production: `https://your-domain.com/api`
- Local Development: `http://localhost/api`

## Authentication

Most endpoints do not require authentication. The `/api/user` endpoint requires Laravel Sanctum authentication via bearer token or session cookie.

## Endpoints

### 1. Get Current User

Retrieve information about the currently authenticated user.

**Endpoint:** `GET /api/user`

**Authentication:** Required (Sanctum)

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  ...
}
```

**Status Codes:**
- `200 OK` - User retrieved successfully
- `401 Unauthorized` - Authentication required

---

### 2. Register User

Create a new user registration request. This endpoint accepts registration data and creates a pending registration that will be processed asynchronously.

**Endpoint:** `POST /api/register`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "user@example.com",
  "region_id": 1,
  "app_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "privacy_policy_accepted": true,
  "aup_accepted": true,
  ...
}
```

**Request Parameters:**
- `email` (string, required) - User's email address
- Additional fields may be accepted and stored in `request_data`

**Response:**
```json
{
  "status": "pending",
  "message": "Registration pending",
  "id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Status Codes:**
- `202 Accepted` - Registration request created successfully
- `500 Internal Server Error` - Error creating registration

**Notes:**
- The registration is processed asynchronously
- Use the returned `id` (UUID) to check registration status via the `GET /api/register/{uuid}` endpoint
- All request data is stored and can be retrieved later

---

### 3. Get Registration Status

Retrieve the status and details of a user registration by UUID.

**Endpoint:** `GET /api/register/{uuid}`

**Authentication:** Not required

**URL Parameters:**
- `uuid` (string, required) - The UUID of the registration

**Response:**
```json
{
  "status": "success",
  "email": "user@example.com",
  "result_data": {
    "app_instance_uuid": "550e8400-e29b-41d4-a716-446655440001",
    "app_url": "https://app.example.com",
    "message": "Registration completed successfully"
  },
  "created_at": "2024-01-15T10:30:00.000000Z",
  "updated_at": "2024-01-15T10:35:00.000000Z"
}
```

**Status Values:**
- `pending` - Registration is pending processing
- `processing` - Registration is currently being processed
- `success` - Registration completed successfully
- `failed` - Registration failed

**Status Codes:**
- `200 OK` - Registration found and returned
- `404 Not Found` - Registration with the provided UUID not found

**Notes:**
- The `result_data` field contains additional information about the registration result, such as app instance details
- If the associated app instance has failed, the registration status will automatically be updated to `failed`

---

### 4. Get Regions and Apps

Retrieve all public regions (stores) and their available apps. This endpoint is useful for displaying available regions and applications in a marketplace or registration interface.

**Endpoint:** `GET /api/regions`

**Authentication:** Not required

**Response:**
```json
{
  "status": "success",
  "message": "Regions and apps retrieved successfully",
  "data": {
    "regions": [
      {
        "uuid": null,
        "id": 1,
        "label": "US East",
        "apps": [
          {
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "label": "My Application"
          }
        ]
      },
      {
        "uuid": null,
        "id": 2,
        "label": "EU West",
        "apps": [
          {
            "uuid": "550e8400-e29b-41d4-a716-446655440001",
            "label": "Another Application"
          }
        ]
      }
    ]
  },
  "status_code": 200
}
```

**Response Fields:**
- `regions` (array) - List of public regions/stores
  - `id` (integer) - Region/store ID
  - `label` (string) - Display name of the region
  - `apps` (array) - List of available apps in this region
    - `uuid` (string) - App UUID
    - `label` (string) - Display name of the app

**Status Codes:**
- `200 OK` - Regions and apps retrieved successfully
- `500 Internal Server Error` - Error retrieving regions

**Notes:**
- Only returns regions with `status = PUBLIC` and `listed_in_marketplace = true`
- Only returns apps with `status = AVAILABLE`
- Regions use `id` as identifier (not UUID)

---

### 5. Update Instance Health Status

Update the health status of a Polydock app instance. This endpoint accepts both GET and POST requests and is typically called by health check systems or deployment scripts.

**Endpoint:** `GET|POST /api/instance/{uuid}/health/{status}`

**Authentication:** Not required

**URL Parameters:**
- `uuid` (string, required) - The UUID of the app instance
- `status` (string, required) - The health status to set

**Query Parameters (GET) or Request Body (POST):**
- Optional debug data can be included and will be logged

**Valid Status Values:**
- `running_healthy_claimed` - Instance is running, healthy, and claimed by a user
- `running_healthy_unclaimed` - Instance is running, healthy, but not yet claimed
- `running_unhealthy` - Instance is running but unhealthy
- `running_unresponsive` - Instance is running but not responding

**Prerequisites:**
The instance must be in one of the following states before a health status update is allowed:
- `running_healthy_claimed`
- `running_healthy_unclaimed`
- `running_unhealthy`
- `running_unresponsive`
- `post_deploy_completed`
- `post_upgrade_completed`

**Response:**
```json
{
  "message": "Health status updated successfully",
  "instance": "550e8400-e29b-41d4-a716-446655440000",
  "status": "running_healthy_claimed",
  "status_code": 200
}
```

**Status Codes:**
- `200 OK` - Health status updated successfully
- `400 Bad Request` - Invalid status value or instance not ready for health check update
- `404 Not Found` - Instance with the provided UUID not found

**Error Responses:**

Invalid status value:
```json
{
  "error": "Invalid status value",
  "status_code": 400
}
```

Invalid running status:
```json
{
  "error": "Invalid running status",
  "status_code": 400,
  "allowed_statuses": [
    "running_healthy_claimed",
    "running_healthy_unclaimed",
    "running_unhealthy",
    "running_unresponsive"
  ]
}
```

Instance not ready for health check:
```json
{
  "error": "Current status is not ready for health check update",
  "status_code": 400
}
```

**Notes:**
- Both GET and POST methods are supported
- Debug data can be included in query parameters (GET) or request body (POST) and will be logged
- The endpoint validates that the status transition is allowed based on the current instance state

---

## Web Routes

### 6. Redirect to App Instance

Redirect to an app instance's URL. This route checks for a one-time login URL first, then falls back to the app URL.

**Endpoint:** `GET /app-instances/{appInstance}`

**Authentication:** Not required

**URL Parameters:**
- `appInstance` (integer|string) - The ID or UUID of the app instance

**Response:**
- `302 Found` - Redirects to the app instance URL
- `404 Not Found` - No URL available for this app instance

**Notes:**
- If a one-time login URL exists and hasn't expired, it will be used
- Otherwise, the app URL will be used
- If neither URL is available, a 404 error is returned

---

## Error Handling

All endpoints follow standard HTTP status codes:

- `200 OK` - Request successful
- `202 Accepted` - Request accepted for processing
- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Authentication required
- `404 Not Found` - Resource not found
- `500 Internal Server Error` - Server error

Error responses typically include:
```json
{
  "error": "Error message",
  "status_code": 400
}
```

Or for registration endpoints:
```json
{
  "status": "error",
  "message": "Error message"
}
```

---

## Rate Limiting

Rate limiting may be applied to API endpoints. Check response headers for rate limit information:
- `X-RateLimit-Limit` - Maximum number of requests allowed
- `X-RateLimit-Remaining` - Number of requests remaining
- `Retry-After` - Seconds to wait before retrying (if rate limited)

---

## Examples

### Register a New User

```bash
curl -X POST https://your-domain.com/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "region_id": 1,
    "app_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "privacy_policy_accepted": true,
    "aup_accepted": true
  }'
```

### Check Registration Status

```bash
curl https://your-domain.com/api/register/550e8400-e29b-41d4-a716-446655440000
```

### Get Available Regions and Apps

```bash
curl https://your-domain.com/api/regions
```

### Update Instance Health Status

```bash
# Using GET
curl "https://your-domain.com/api/instance/550e8400-e29b-41d4-a716-446655440000/health/running_healthy_claimed?debug_info=some_data"

# Using POST
curl -X POST https://your-domain.com/api/instance/550e8400-e29b-41d4-a716-446655440000/health/running_healthy_claimed \
  -H "Content-Type: application/json" \
  -d '{
    "debug_info": "some_data"
  }'
```

---

## Versioning

Currently, there is no API versioning in place. All endpoints are under `/api`. Future versions may introduce versioned endpoints (e.g., `/api/v1/...`).

---

## Support

For questions or issues with the API, please contact the development team or refer to the main [Documentation](README.md).

