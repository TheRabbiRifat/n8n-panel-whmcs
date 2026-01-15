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
        'AdminSingleSignOnLabel' => 'Login to n8n Panel',
        'ServiceSingleSignOnLabel' => 'Login to Panel',
    );
}

function n8n_panel_ConfigOptions()
{
    return array(
        'Package ID' => array(
            'Type' => 'text',
            'Size' => '25',
            'Loader' => 'n8n_panel_PackageLoader',
            'SimpleMode' => true,
            'Description' => 'Select a package from the n8n Host Manager',
        ),
        'n8n Version' => array(
            'Type' => 'dropdown',
            'Options' => 'stable,latest,beta',
            'Default' => 'latest',
            'Description' => 'Docker tag',
        ),
    );
}

function n8n_panel_PackageLoader(array $params)
{
    // Try to get server details from params (if server group selected)
    // or fallback to finding the first active server (common practice for config loaders)

    $hostname = '';
    $password = '';
    $secure = false;

    if (!empty($params['serverhostname']) || !empty($params['serverip'])) {
        $hostname = !empty($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
        $password = $params['serverpassword']; // Already decrypted in Loader params context? Usually yes.
        $secure = (isset($params['serversecure']) && $params['serversecure'] == 'on');
    } else {
        // Fallback: Find first active server
        $server = Capsule::table('tblservers')
            ->where('type', 'n8n_panel')
            ->where('disabled', 0)
            ->first();

        if ($server) {
            $hostname = !empty($server->hostname) ? $server->hostname : $server->ipaddress;
            $password = decrypt($server->password);
            $secure = ($server->secure == 'on');
        } else {
            throw new Exception("No active n8n Panel server found.");
        }
    }

    // Construct Base URL
    if (!preg_match("~^https?://~i", $hostname)) {
        $hostname = "https://" . $hostname;
    }

    $parts = parse_url($hostname);
    if (!isset($parts['port'])) {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $hostname = $scheme . $host . ':8448' . $path;
    }

    $baseUrl = rtrim($hostname, '/') . '/api/integration';

    try {
        $client = new N8nHostManagerClient($baseUrl, $password, $secure);
        $response = $client->getPackages();

        $list = [];
        if (isset($response['packages'])) {
            foreach ($response['packages'] as $pkg) {
                // Return Name as key and value
                $list[$pkg['name']] = $pkg['name'];
            }
        }
        return $list;

    } catch (Exception $e) {
        throw new Exception("Failed to fetch packages: " . $e->getMessage());
    }
}

function n8n_panel_getPackageIdByName(N8nHostManagerClient $client, $name)
{
    $response = $client->getPackages();
    if (isset($response['packages'])) {
        foreach ($response['packages'] as $pkg) {
            if ($pkg['name'] === $name) {
                return $pkg['id'];
            }
        }
    }
    throw new Exception("Package not found: " . $name);
}

function n8n_panel_getClient($params)
{
    $hostname = $params['serverhostname'];
    if (empty($hostname)) {
        $hostname = $params['serverip'];
    }

    $verifySsl = (isset($params['serversecure']) && $params['serversecure'] == 'on');

    if (!preg_match("~^https?://~i", $hostname)) {
        $hostname = "https://" . $hostname;
    }

    $parts = parse_url($hostname);
    if (!isset($parts['port'])) {
         $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://';
         $host = isset($parts['host']) ? $parts['host'] : '';
         $path = isset($parts['path']) ? $parts['path'] : '';
         $hostname = $scheme . $host . ':8448' . $path;
    }

    $baseUrl = rtrim($hostname, '/') . '/api/integration';

    return new N8nHostManagerClient($baseUrl, $params['serverpassword'], $verifySsl);
}

function n8n_panel_TestConnection(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $result = $client->testConnection();

        if (isset($result['status']) && $result['status'] == 'success') {
            return array(
                'success' => true,
            );
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
        // ConfigOption1 now contains Package Name
        $packageName = $params['configoption1'];
        $n8nVersion = isset($params['configoption2']) ? $params['configoption2'] : 'latest';
        $productType = $params['producttype'];

        if ($productType === 'reselleraccount') {
            try {
                $result = $client->createReseller($firstName . ' ' . $lastName, $email, $password);

                // For Reseller, we might also need package limits?
                // API `createReseller` takes instance_limit. Maybe mapped from package?
                // But current API call just passes name/email/pass.
                // We will proceed as before.

                $client->createReseller($firstName . ' ' . $lastName, $username, $email, $password);

                 Capsule::table('tblhosting')
                    ->where('id', $params['serviceid'])
                    ->update([
                        'username' => $username,
                        'domain' => '',
                    ]);

                return 'success';
            } catch (Exception $e) {
                return $e->getMessage();
            }

        } else {
            // Resolve Package Name to ID
            $packageId = n8n_panel_getPackageIdByName($client, $packageName);

            // Generate Instance Name
            $chars = 'abcdefghijklmnopqrstuvwxyz123456789';
            $instanceName = '';
            for ($i = 0; $i < 7; $i++) {
                $instanceName .= $chars[mt_rand(0, strlen($chars) - 1)];
            }

            // 2. Create Instance
            $result = $client->createInstance($email, $packageId, $instanceName, $n8nVersion);

            if (isset($result['status']) && $result['status'] == 'success') {
                $domain = $result['domain'];

                Capsule::table('tblhosting')
                    ->where('id', $params['serviceid'])
                    ->update([
                        'username' => $instanceName,
                        'domain' => $domain,
                    ]);

                return 'success';
            } else {
                return 'Failed to create instance: ' . json_encode($result);
            }
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
        $newPackageName = $params['configoption1'];

        if (empty($instanceId)) {
            return "Instance ID not found in username field.";
        }

        $newPackageId = n8n_panel_getPackageIdByName($client, $newPackageName);

        $client->upgradeInstance($instanceId, $newPackageId);
        return 'success';
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function n8n_panel_AdminServicesTabFields(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $username = $params['username'];
        $productType = $params['producttype'];

        if (!empty($username)) {

            if ($productType === 'reselleraccount') {
                $stats = $client->getResellerStats($username);

                if (isset($stats['status']) && $stats['status'] == 'success') {
                    return array(
                        'Total Instances' => $stats['counts']['instances_total'],
                        'Running Instances' => $stats['counts']['instances_running'],
                        'Stopped Instances' => $stats['counts']['instances_stopped'],
                    );
                }

            } else {
                $stats = $client->getInstanceStats($username);

                if (isset($stats['status']) && $stats['status'] == 'success') {
                    return array(
                        'Instance Status' => ucfirst($stats['instance_status']),
                        'CPU Usage' => $stats['cpu_percent'] . '%',
                        'Memory Usage' => $stats['memory_usage'] . ' / ' . $stats['memory_limit'] . ' (' . $stats['memory_percent'] . '%)',
                        'Domain' => '<a href="http://' . $stats['domain'] . '" target="_blank">' . $stats['domain'] . '</a>',
                    );
                }
            }
        }
    } catch (Exception $e) {
        return array(
            'Instance Status' => 'Error retrieving stats: ' . $e->getMessage(),
        );
    }

    return array();
}

function n8n_panel_AdminSingleSignOn(array $params)
{
    try {
        $client = n8n_panel_getClient($params);

        // 1. Get Admin Username associated with the API Token
        $connectionData = $client->testConnection();

        // Use 'username' from response if available, otherwise assume 'name' is the username or try to use email as fallback if strictly necessary,
        // but user said "no emails". We assume API returns 'username' for the token owner now.
        // If not present, we might fail or try 'name'.

        $adminUsername = '';
        if (isset($connectionData['user']['username'])) {
            $adminUsername = $connectionData['user']['username'];
        } elseif (isset($connectionData['user']['name'])) {
             $adminUsername = $connectionData['user']['name'];
        }

        if (empty($adminUsername)) {
             return array(
                'success' => false,
                'errorMsg' => "Could not retrieve Admin Username from connection test.",
            );
        }

        // 2. Get SSO URL for Admin
        $result = $client->getUserSso($adminUsername);

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

function n8n_panel_ServiceSingleSignOn(array $params)
{
    try {
        $productType = $params['producttype'];
        $client = n8n_panel_getClient($params);
        $username = $params['username'];
        if (empty($username)) {
             $username = $params['clientsdetails']['email'];
        }

        if ($productType === 'reselleraccount') {
            $result = $client->getResellerSso($username);
        } else {
            // Standard User SSO
            $result = $client->getUserSso($username);
        }

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

function n8n_panel_AdminLink(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $username = $params['serverusername'];

        $result = $client->getUserSso($username);

        if (isset($result['status']) && $result['status'] == 'success' && isset($result['redirect_url'])) {
             $url = $result['redirect_url'];
             return '<a href="' . $url . '" target="_blank" class="btn btn-primary">Login to n8n Panel</a>';
        }

        // Fallback to base URL
        $hostname = $params['serverhostname'] ?: $params['serverip'];
        if (!preg_match("~^https?://~i", $hostname)) $hostname = "https://" . $hostname;

        return '<a href="' . $hostname . '" target="_blank" class="btn btn-default">Visit Panel</a>';

    } catch (Exception $e) {
        return '<p class="text-danger">Error: ' . $e->getMessage() . '</p>';
    }
}

function n8n_panel_LoginLink(array $params)
{
    try {
        $productType = $params['producttype'];
        $client = n8n_panel_getClient($params);
        $username = $params['username'];

        if ($productType === 'reselleraccount') {
             if (empty($username)) return '';

             $result = $client->getResellerSso($username);
             if (isset($result['status']) && $result['status'] == 'success' && isset($result['redirect_url'])) {
                 $url = $result['redirect_url'];
                 return '<a href="' . $url . '" target="_blank">Login to Control Panel</a>';
             }
        } elseif (!empty($params['domain'])) {
            $url = 'http://' . $params['domain'];
            return '<a href="' . $url . '" target="_blank">Visit Instance</a>';
        }
    } catch (Exception $e) {
        return '';
    }
    return '';
}


function n8n_panel_ClientAreaCustomButtonArray(array $params)
{
    $productType = $params['producttype'];

    if ($productType === 'reselleraccount') {
        return array();
    }

    return array(
        "Start Instance" => "startInstance",
        "Stop Instance" => "stopInstance",
    );
}

function n8n_panel_AdminCustomButtonArray(array $params)
{
    $productType = $params['producttype'];

    if ($productType === 'reselleraccount') {
        return array();
    }

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
        $productType = $params['producttype'];

        if ($productType === 'reselleraccount') {

            $systemStats = $client->getResellerStats($instanceId);

            return [
                'tabOverviewReplacementTemplate' => 'manage',
                'vars' => [
                    'productType' => $productType,
                    'systemStats' => $systemStats,
                ],
            ];

        } elseif (!empty($instanceId)) {

            $stats = $client->getInstanceStats($instanceId);

            return [
                'tabOverviewReplacementTemplate' => 'manage',
                'vars' => [
                    'productType' => $productType,
                    'instanceStats' => $stats,
                ],
            ];
        }

    } catch (Exception $e) {
        logModuleCall(
            'n8n_panel',
            __FUNCTION__,
            $params,
            null,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return [];
}
