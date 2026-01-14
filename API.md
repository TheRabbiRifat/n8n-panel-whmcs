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
      "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    }
    ```

#### Get System Stats
Retrieve server health metrics and usage counts.

*   **Endpoint:** `GET /system/stats`
*   **Response:**
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

---

### 2. Instance Management

#### Create Instance
Provision a new n8n instance for an existing user.

*   **Endpoint:** `POST /instances/create`
*   **Body Parameters:**
    *   `email` (string, required): Existing user email.
    *   `package` (string, required): Name of the resource package.
    *   `name` (string, required): Unique instance name (alpha-dash).
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

*   **Endpoint:** `GET /instances/{name}/stats`
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

*   **Start:** `POST /instances/{name}/start`
*   **Stop:** `POST /instances/{name}/stop`
*   **Suspend:** `POST /instances/{name}/suspend` (Stops and marks as suspended)
*   **Unsuspend:** `POST /instances/{name}/unsuspend` (Unmarks and starts)
*   **Terminate:** `POST /instances/{name}/terminate` (Permanently deletes data)

**Response:**
```json
{ "status": "success" }
```

#### Upgrade Package
Change the resource package for an instance. New limits are applied immediately.

*   **Endpoint:** `POST /instances/{name}/upgrade`
*   **Body Parameters:**
    *   `package` (string, required): New package Name.
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
        { "id": 1, "name": "Starter", "cpu_limit": 1.0, "ram_limit": 1.0, "disk_limit": 10 }
      ]
    }
    ```

#### Get Package Details
*   **Endpoint:** `GET /packages/{id}`

#### Create User
Create a new standard user. (Admin or Reseller)

*   **Endpoint:** `POST /users`
*   **Body Parameters:**
    *   `name`, `email`, `password` (all required)
*   **Response:**
    ```json
    { "status": "success", "user_id": 20 }
    ```

#### Create Reseller
Create a new user with 'reseller' role.

*   **Endpoint:** `POST /resellers`
*   **Body Parameters:**
    *   `name`, `email`, `password` (all required)
*   **Response:**
    ```json
    { "status": "success", "user_id": 15 }
    ```

#### User SSO
Generate a temporary auto-login URL for a specific user.

*   **Endpoint:** `POST /users/sso`
*   **Body Parameters:**
    *   `email` (string, required): Email of the user to log in as.
*   **Response:**
    ```json
    {
      "status": "success",
      "redirect_url": "https://panel-domain.com/sso/login/5?signature=..."
    }
    ```

---

## Error Handling

The API returns standard HTTP status codes:
*   `200/201`: Success
*   `401`: Unauthenticated (Missing/Invalid Token)
*   `403`: Unauthorized (Insufficient Permissions or IP not whitelisted)
*   `404`: Resource Not Found
*   `422`: Validation Error
*   `429`: Too Many Requests
*   `500`: Server Error
