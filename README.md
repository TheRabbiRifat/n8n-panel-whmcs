# n8n Panel WHMCS Module

This module allows you to provision and manage n8n instances within WHMCS using the n8n Host Manager API.

## Features

*   **Provisioning:** Automatically create n8n instances upon order payment.
*   **Account Management:** Suspend, Unsuspend, and Terminate instances.
*   **Upgrades:** Change packages (upgrade/downgrade resources).
*   **SSO:** Single Sign-On from the WHMCS Client Area to the n8n Panel.
*   **Client Area:** View instance status, CPU/RAM usage, and control power (Start/Stop).
*   **Configuration:** Custom API Port and SSL Verification options.

## Installation

1.  Upload the `modules/servers/n8n_panel` directory to your WHMCS installation at `/path/to/whmcs/modules/servers/`.

## Configuration

1.  Log in to your WHMCS Admin Area.
2.  Go to **System Settings > Products/Services > Servers**.
3.  Add a new server:
    *   **Name:** Any name (e.g., "n8n Host Manager").
    *   **Hostname:** The hostname of your n8n Host Manager (e.g., `panel.example.com`). If using a custom port, append it (e.g., `panel.example.com:8448`). Defaults to **8448**.
    *   **Type:** Select **n8n Panel**.
    *   **Password:** Enter your **API Token** here.
    *   **Secure:** Check this box to enable SSL Verification (Recommended). Uncheck to skip verification.
4.  Save Changes.

### Product Setup

1.  Go to **System Settings > Products/Services > Products/Services**.
2.  Create or Edit a product.
3.  Click the **Module Settings** tab.
4.  **Module Name:** Select **n8n Panel**.
5.  **Server Group:** Select the group containing your n8n server.
6.  **Package:** Select the desired package from the dropdown (dynamically fetched from the server).
7.  **n8n Version:** Select the version tag (stable, latest, beta).
8.  Select **Automatically setup the product as soon as the first payment is received**.
9.  Save Changes.

## Requirements

*   WHMCS v8.0+
*   PHP 7.4 or later
*   n8n Host Manager API access
