<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/N8nHostManagerClient.php';

use WHMCS\Module\Server\ProvisioningModule\N8nHostManagerClient;

function provisioningmodule_MetaData()
{
    return array(
        'DisplayName' => 'n8n Host Manager',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'ServiceSingleSignOnLabel' => 'Login to n8n Panel',
    );
}

function provisioningmodule_ConfigOptions()
{
    return array(
        'Package ID' => array(
            'Type' => 'text',
            'Size' => '10',
            'Description' => 'The ID of the resource package in n8n Host Manager',
        ),
    );
}

function provisioningmodule_getClient($params)
{
    // serverhostname usually comes as "hostname" or "ip"
    // serverpassword is used for the API Token

    $hostname = $params['serverhostname'];
    if (empty($hostname)) {
        $hostname = $params['serverip'];
    }

    // Ensure protocol is present
    if (!preg_match("~^https?://~i", $hostname)) {
        $hostname = "https://" . $hostname;
    }

    // Append /api/integration if not present (based on API.md Base URL)
    // API.md says: https://your-panel-domain.com/api/integration
    // If the user enters "panel-domain.com" as hostname, we should append /api/integration
    // But the client class appends endpoint to baseUrl.
    // So we should construct the base URL correctly.

    $baseUrl = rtrim($hostname, '/') . '/api/integration';

    return new N8nHostManagerClient($baseUrl, $params['serverpassword']);
}

function provisioningmodule_TestConnection(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_CreateAccount(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);

        $email = $params['clientsdetails']['email'];
        $firstName = $params['clientsdetails']['firstname'];
        $lastName = $params['clientsdetails']['lastname'];
        $password = $params['password'];
        $packageId = $params['configoption1']; // Corresponds to Package ID

        // Instance Name: use domain or fallback
        $instanceName = $params['domain'];
        if (empty($instanceName)) {
            $instanceName = 'n8n-' . $params['serviceid'];
        }
        // Sanitize name (alpha-dash)
        $instanceName = preg_replace('/[^a-zA-Z0-9-]/', '-', $instanceName);

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

function provisioningmodule_SuspendAccount(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_UnsuspendAccount(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_TerminateAccount(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_ChangePackage(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_ServiceSingleSignOn(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_ClientAreaCustomButtonArray()
{
    return array(
        "Start Instance" => "startInstance",
        "Stop Instance" => "stopInstance",
    );
}

function provisioningmodule_AdminCustomButtonArray()
{
    return array(
        "Start Instance" => "startInstance",
        "Stop Instance" => "stopInstance",
    );
}

function provisioningmodule_startInstance(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_stopInstance(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
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

function provisioningmodule_ClientArea(array $params)
{
    try {
        $client = provisioningmodule_getClient($params);
        $instanceId = $params['username'];

        if (!empty($instanceId)) {
             $stats = $client->getInstanceStats($instanceId);

             return array(
                'tabOverviewReplacementTemplate' => 'templates/overview.tpl',
                'templateVariables' => array(
                    'instanceStats' => $stats,
                ),
            );
        }

    } catch (Exception $e) {
        // Log error but allow page to load
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return array();
}
