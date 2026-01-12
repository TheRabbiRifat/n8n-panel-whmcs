<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/N8nHostManagerClient.php';

use WHMCS\Module\Server\N8nPanel\N8nHostManagerClient;

function n8n_panel_MetaData()
{
    return array(
        'DisplayName' => 'n8n Panel',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'ServiceSingleSignOnLabel' => 'Login to n8n Panel',
    );
}

function n8n_panel_ConfigOptions()
{
    return array(
        'Package ID' => array(
            'Type' => 'text',
            'Size' => '10',
            'Description' => 'The ID of the resource package in n8n Host Manager',
        ),
        'API Port' => array(
            'Type' => 'text',
            'Size' => '5',
            'Description' => 'Override default port (optional)',
        ),
        'Skip SSL Verification' => array(
            'Type' => 'yesno',
            'Description' => 'Tick to skip SSL verification (not recommended)',
        ),
    );
}

function n8n_panel_getClient($params)
{
    // serverhostname usually comes as "hostname" or "ip"
    // serverpassword is used for the API Token

    $hostname = $params['serverhostname'];
    if (empty($hostname)) {
        $hostname = $params['serverip'];
    }

    // Check if custom port is set in Config Options (Index 2)
    // Note: ConfigOptions order matters.
    // 1: Package ID, 2: API Port, 3: Skip SSL Verification
    $customPort = isset($params['configoption2']) ? trim($params['configoption2']) : '';
    $skipSsl = isset($params['configoption3']) && $params['configoption3'] == 'on';

    // Ensure protocol is present
    if (!preg_match("~^https?://~i", $hostname)) {
        $hostname = "https://" . $hostname;
    }

    // If custom port is provided, insert it into the hostname if not already present
    if (!empty($customPort)) {
        $parts = parse_url($hostname);
        if (!isset($parts['port'])) {
             // Reconstruct URL with port
             $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://';
             $host = isset($parts['host']) ? $parts['host'] : '';
             $path = isset($parts['path']) ? $parts['path'] : '';
             $hostname = $scheme . $host . ':' . $customPort . $path;
        }
    }

    // Append /api/integration if not present (based on API.md Base URL)
    // API.md says: https://your-panel-domain.com/api/integration
    // If the user enters "panel-domain.com" as hostname, we should append /api/integration
    // But the client class appends endpoint to baseUrl.
    // So we should construct the base URL correctly.

    $baseUrl = rtrim($hostname, '/') . '/api/integration';

    return new N8nHostManagerClient($baseUrl, $params['serverpassword'], !$skipSsl);
}

function n8n_panel_TestConnection(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $result = $client->testConnection();

        if (isset($result['status']) && $result['status'] == 'success') {
            return array('success' => true);
        } else {
            return array('success' => false, 'error' => 'Connection failed: ' . json_encode($result));
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => $e->getMessage(),
        );
    }
}

function n8n_panel_CreateAccount(array $params)
{
    try {
        $client = n8n_panel_getClient($params);

        $email = $params['clientsdetails']['email'];
        $firstName = $params['clientsdetails']['firstname'];
        $lastName = $params['clientsdetails']['lastname'];
        $password = $params['password'];
        $packageId = $params['configoption1']; // Corresponds to Package ID

        // Generate Instance Name: 7 digit random (a-z, 1-9)
        $chars = 'abcdefghijklmnopqrstuvwxyz123456789';
        $instanceName = '';
        for ($i = 0; $i < 7; $i++) {
            $instanceName .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        // 1. Ensure user exists
        try {
            $client->createUser($firstName . ' ' . $lastName, $email, $password);
        } catch (Exception $e) {
            // Ignore if user likely exists or other non-critical error for creation
            // Ideally we check specific error code, but API.md doesn't specify "User already exists" code/message exactly
            // It says 422 Validation Error.
            // We proceed to create instance.
        }

        // 2. Create Instance
        $result = $client->createInstance($email, $packageId, $instanceName);

        if (isset($result['status']) && $result['status'] == 'success') {
            $instanceId = $result['instance_id'];
            $domain = $result['domain'];

            // Update Service with Instance ID (store in username) and Domain
            $serviceId = $params['serviceid'];

            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'username' => $instanceId,
                    'domain' => $domain,
                ]);

            return 'success';
        } else {
            return 'Failed to create instance: ' . json_encode($result);
        }

    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_SuspendAccount(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (empty($instanceId)) {
            return "Instance ID not found in username field.";
        }

        $client->suspendInstance($instanceId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_UnsuspendAccount(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (empty($instanceId)) {
            return "Instance ID not found in username field.";
        }

        $client->unsuspendInstance($instanceId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_TerminateAccount(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (empty($instanceId)) {
            return "Instance ID not found in username field.";
        }

        $client->terminateInstance($instanceId);

        // Clear the username/domain?
         Capsule::table('tblhosting')
                ->where('id', $params['serviceid'])
                ->update([
                    'username' => '',
                    'domain' => '',
                ]);

        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_ChangePackage(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];
        $newPackageId = $params['configoption1'];

        if (empty($instanceId)) {
            return "Instance ID not found in username field.";
        }

        $client->upgradeInstance($instanceId, $newPackageId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_ServiceSingleSignOn(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $email = $params['clientsdetails']['email'];

        $result = $client->getUserSso($email);

        if (isset($result['status']) && $result['status'] == 'success' && isset($result['redirect_url'])) {
             return array(
                'success' => true,
                'redirectTo' => $result['redirect_url'],
            );
        }

        return array(
            'success' => false,
            'errorMsg' => "Failed to get SSO URL",
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'errorMsg' => $e->getMessage(),
        );
    }
}

function n8n_panel_ClientAreaCustomButtonArray()
{
    return array(
        "Start Instance" => "startInstance",
        "Stop Instance" => "stopInstance",
    );
}

function n8n_panel_AdminCustomButtonArray()
{
    return array(
        "Start Instance" => "startInstance",
        "Stop Instance" => "stopInstance",
    );
}

function n8n_panel_startInstance(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (empty($instanceId)) {
            return "Instance ID not found.";
        }

        $client->startInstance($instanceId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_stopInstance(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (empty($instanceId)) {
            return "Instance ID not found.";
        }

        $client->stopInstance($instanceId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_ClientArea(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (!empty($instanceId)) {
             $stats = $client->getInstanceStats($instanceId);

             return array(
                'tabOverviewReplacementTemplate' => 'modules/servers/n8n_panel/templates/overview.tpl',
                'templateVariables' => array(
                    'instanceStats' => $stats,
                ),
            );
        }

    } catch (Exception $e) {
        // Log error but allow page to load
        logModuleCall(
            'n8n_panel',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return array();
}
