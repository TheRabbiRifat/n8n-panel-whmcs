# n8n Host Manager API Documentation

This API enables external systems (such as WHMCS, billing platforms, or custom dashboards) to manage n8n instances, packages, and system resources.

## Base URL

All API requests should be sent to:
```
https://your-panel-domain.com/api/integration
```

## Authentication

The API uses **Bearer Token** authentication. You must include your API Token in the `Authorization` header for every request.

```http
Authorization: Bearer <your_api_token>
Content-Type: application/json
Accept: application/json
```

*You can generate API Tokens from the "Manage API Tokens" section in your user profile.*

---

## Endpoints

### 1. Connection & System

#### Test Connection
Verify your API token is valid and the server is reachable.

*   **Endpoint:** `GET /connection/test`
*   **Response:**
    ```json
    {
      "status": "success",
      "message": "Connection successful",
      "hostname": "server-01.example.com",
      "ip": "192.168.1.100",
      "detected_url": "https://your-panel-domain.com",
      "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    }
    ```

#### Get System Stats
Retrieve server health metrics and usage counts. The response varies based on user role (Admin vs Reseller).

*   **Endpoint:** `GET /system/stats`
*   **Response (Admin):**
    ```json
    {
      "status": "success",
      "server_status": "online",
      "system_info": {
        "os": "Ubuntu 22.04 LTS",
        "kernel": "5.15.0-91-generic",
        "ip": "192.168.1.10",
        "uptime": "2 days, 4 hours",
        "hostname": "server-01"
      },
      "load_averages": { "1": 0.5, "5": 0.3, "15": 0.1 },
      "counts": {
        "users": 5,
        "instances_total": 10,
        "instances_running": 8,
        "instances_stopped": 2
      }
    }
    ```
*   **Response (Reseller):**
    ```json
    {
      "status": "success",
      "server_status": "online",
      "load_averages": { "1": 0.5, "5": 0.3, "15": 0.1 },
      "counts": {
        "users": 2,
        "instances_total": 3
      }
    }
    ```

---

### 2. Instance Management

#### Create Instance
Provision a new n8n instance for an existing user.

*   **Endpoint:** `POST /instances/create`
*   **Body Parameters:**
    *   `email` (string, required): Existing user email.
    *   `package_id` (int, required): ID of the resource package.
    *   `name` (string, required): Unique instance name (alpha-dash, used for subdomain).
    *   `version` (string, optional): n8n version tag (default: 'latest').
*   **Response:**
    ```json
    {
      "status": "success",
      "instance_id": 12,
      "domain": "my-instance.panel-domain.com",
      "user_id": 5
    }
    ```

#### Get Instance Stats
Get real-time resource usage for a specific instance.

*   **Endpoint:** `GET /instances/{id}/stats`
*   **Response:**
    ```json
    {
      "status": "success",
      "domain": "my-instance.panel-domain.com",
      "instance_status": "running",
      "cpu_percent": 0.15,
      "memory_usage": "250MiB",
      "memory_limit": "1GiB",
      "memory_percent": 24.4
    }
    ```

#### Instance Power Actions
Perform power operations on an instance.

*   **Start:** `POST /instances/{id}/start`
*   **Stop:** `POST /instances/{id}/stop`
*   **Suspend:** `POST /instances/{id}/suspend` (Stops and marks as suspended)
*   **Unsuspend:** `POST /instances/{id}/unsuspend` (Unmarks and starts)
*   **Terminate:** `POST /instances/{id}/terminate` (Permanently deletes data)

**Response:**
```json
{ "status": "success" }
```

#### Upgrade Package
Change the resource package for an instance. New limits are applied immediately via live update.

*   **Endpoint:** `POST /instances/{id}/upgrade`
*   **Body Parameters:**
    *   `package_id` (int, required): New package ID.
*   **Response:**
    ```json
    {
      "status": "success",
      "message": "Package updated and resources applied.",
      "new_package": "Pro Plan"
    }
    ```

---

### 3. Packages & Resellers

#### List Packages
Get all available resource packages.

*   **Endpoint:** `GET /packages`
*   **Response:**
    ```json
    {
      "status": "success",
      "packages": [
        { "id": 1, "name": "Starter", "cpu_limit": 1.0, "ram_limit": 1.0, "disk_limit": 10, "is_active": true }
      ]
    }
    ```

#### Get Package Details
Get details for a single package.

*   **Endpoint:** `GET /packages/{id}`
*   **Response:**
    ```json
    {
      "status": "success",
      "package": {
        "id": 1,
        "name": "Starter",
        "cpu_limit": 1.0,
        "ram_limit": 1.0,
        "disk_limit": 10,
        "created_at": "2024-01-01T00:00:00.000000Z"
      }
    }
    ```

#### Create User
Create a new standard user.

*   **Endpoint:** `POST /users`
*   **Body Parameters:**
    *   `name` (string, required): Full Name.
    *   `email` (string, required): Valid Email Address.
    *   `password` (string, required): Minimum 8 characters.
*   **Response:**
    ```json
    { "status": "success", "user_id": 20 }
    ```

#### Create Reseller
Create a new user with the 'reseller' role. (Admin only)

*   **Endpoint:** `POST /resellers`
*   **Body Parameters:**
    *   `name` (string, required): Full Name.
    *   `email` (string, required): Valid Email Address.
    *   `password` (string, required): Minimum 8 characters.
*   **Response:**
    ```json
    { "status": "success", "user_id": 15 }
    ```

#### User SSO
Generate a temporary auto-login URL for a specific user. Resellers can only access their own users.

*   **Endpoint:** `POST /users/sso`
*   **Body Parameters:**
    *   `email` (string, required): Email of the user to log in as.
*   **Response:**
    ```json
    {
      "status": "success",
      "redirect_url": "https://panel-domain.com/sso/login/5?expires=1704067200&signature=..."
    }
    ```

---

## Error Handling

The API returns standard HTTP status codes:
*   `200/201`: Success
*   `401`: Unauthenticated (Missing/Invalid Token)
*   `403`: Unauthorized (Insufficient Permissions or IP not whitelisted)
*   `404`: Resource Not Found
*   `422`: Validation Error (Check `errors` object in response body)
*   `429`: Too Many Requests (Rate limit: 60 requests/minute)
*   `500`: Server Error
