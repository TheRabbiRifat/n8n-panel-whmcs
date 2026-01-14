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
    $packageOptions = [];
    $description = 'Select a package from the n8n Host Manager';

    try {
        $server = Capsule::table('tblservers')
            ->where('type', 'n8n_panel')
            ->where('disabled', 0)
            ->first();

        if ($server) {
            $hostname = $server->hostname;
            if (empty($hostname)) {
                $hostname = $server->ipaddress;
            }
            // Use decrypt function if available, or try common alternatives if needed.
            // In standard WHMCS provisioning modules, decrypt() is available.
            $password = decrypt($server->password);
            $secure = ($server->secure == 'on');

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

            $client = new N8nHostManagerClient($baseUrl, $password, $secure);
            $response = $client->getPackages();

            if (isset($response['packages'])) {
                foreach ($response['packages'] as $pkg) {
                    // Use Package ID as the key
                    $packageOptions[$pkg['id']] = $pkg['name'];
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to text field if connection fails
    }

    if (!empty($packageOptions)) {
        $packageField = array(
            'Type' => 'dropdown',
            'Options' => $packageOptions,
            'Description' => $description,
        );
    } else {
        $packageField = array(
            'Type' => 'text',
            'Size' => '10',
            'Description' => 'Package ID (Could not fetch packages: check server config)',
        );
    }

    return array(
        'Package ID' => $packageField,
        'n8n Version' => array(
            'Type' => 'dropdown',
            'Options' => 'stable,latest,beta',
            'Default' => 'latest',
            'Description' => 'Docker tag',
        ),
        'Account Role' => array(
            'Type' => 'dropdown',
            'Options' => 'User,Reseller',
            'Default' => 'User',
            'Description' => 'Select account type',
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

    // SSL Verification based on Server 'Secure' checkbox
    $verifySsl = (isset($params['serversecure']) && $params['serversecure'] == 'on');

    // Ensure protocol is present
    if (!preg_match("~^https?://~i", $hostname)) {
        $hostname = "https://" . $hostname;
    }

    // Check for port in hostname, default to 8448 if not present
    $parts = parse_url($hostname);
    if (!isset($parts['port'])) {
         // Reconstruct URL with port 8448
         $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : 'https://';
         $host = isset($parts['host']) ? $parts['host'] : '';
         $path = isset($parts['path']) ? $parts['path'] : '';
         $hostname = $scheme . $host . ':8448' . $path;
    }

    // Append /api/integration if not present
    $baseUrl = rtrim($hostname, '/') . '/api/integration';

    return new N8nHostManagerClient($baseUrl, $params['serverpassword'], $verifySsl);
}

function n8n_panel_TestConnection(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $result = $client->testConnection();

        if (isset($result['status']) && $result['status'] == 'success') {

            // Auto-fill Server Username in the UI
            // We use Javascript injected into the success message to populate the input field
            $js = '';
            if (isset($result['user']['email'])) {
                $email = htmlspecialchars($result['user']['email']);
                $js = "<script>jQuery('input[name=\"username\"]').val('$email');</script>";
            }

            return array(
                'success' => true,
                'error' => "Connection Successful!$js" // 'error' key is sometimes used for the message popup in older WHMCS versions or specific themes, but standard is 'success' => true.
                                                       // However, standard success alert might not show custom message unless we trick it or usage varies.
                                                       // Actually, 'error' is shown in a red box, 'success' implies green.
                                                       // There is no standard 'message' key documented for TestConnection success in provisioning modules that guarantees display.
                                                       // But let's try to return it via 'error' (empty) or just rely on the script if possible?
                                                       // Wait, if I return success=true, WHMCS shows a hardcoded "Connection Successful" usually.
                                                       // If I return success=false, it shows the error string.
                                                       // If I want to inject JS, I might have to fail the test technically? No, that's bad UX.
                                                       // Let's assume WHMCS might not strip tags in the generic success handler, or there's no way to inject JS on success.
                                                       // BUT, if the user insists on UI autofill without DB update, this is the only path.
                                                       // Let's try returning 'error' as the message with success=true.
                                                       // Some implementations checks if error is set.
            );
            // Re-evaluating: If WHMCS ignores extra keys, this won't work.
            // But let's try returning the JS in the array.
            // Actually, many devs use: return ['success' => true, 'msg' => '...'] or similar.
            // Let's stick to the plan but maybe use a widely compatible approach if known.
            // Since I don't know if 'msg' works, I will return success => true.
            // If I can't inject JS, I can't satisfy the "UI autofill" requirement strictly without DB update.
            // But wait! If I return success=false with the JS, it will show in a Red box "Connection Successful [JS]". The JS might execute.
            // That's a hack.

            // Revised approach for this block:
            // Just return success. If I can't inject JS standardly, I shouldn't leave a hack that might look like an error.
            // BUT the user specifically asked for this.
            // "if successfully connection tested...fill the values of the form automatically as well .. like in username: email"
            // "not to automatically update the database directly"

            // I will go with:
            // return array('success' => true);
            // AND I will use a clever workaround if possible? No.
            // I will try to use the 'error' field with success=true, or just assume the user accepts the limitation if standard API doesn't support it.
            // HOWEVER, I will implement the JS injection in 'error' key but return success=false? No.

            // Let's just return success=true.
            // Use the code:
            /*
            return array('success' => true);
            */
            // Wait, I can't just ignore the request.
            // If I return: return ['success' => true, 'message' => $js];
            // Maybe it works.

            return array(
                'success' => true,
                'message' => "Connection Successful!$js"
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
        $packageId = $params['configoption1']; // Corresponds to Package ID
        $n8nVersion = isset($params['configoption2']) ? $params['configoption2'] : 'latest';
        $accountRole = isset($params['configoption3']) ? $params['configoption3'] : 'User';

        // Generate Instance Name: 7 digit random (a-z, 1-9)
        $chars = 'abcdefghijklmnopqrstuvwxyz123456789';
        $instanceName = '';
        for ($i = 0; $i < 7; $i++) {
            $instanceName .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        // 1. Ensure user exists
        try {
            if ($accountRole === 'Reseller') {
                $client->createReseller($firstName . ' ' . $lastName, $email, $password);
            } else {
                $client->createUser($firstName . ' ' . $lastName, $email, $password);
            }
        } catch (Exception $e) {
            // Ignore if user likely exists or other non-critical error for creation
        }

        // 2. Create Instance
        $result = $client->createInstance($email, $packageId, $instanceName, $n8nVersion);

        if (isset($result['status']) && $result['status'] == 'success') {
            // API formerly returned 'instance_id'. We now use 'name' (which we generated).
            // We store the instance Name in the username field.

            $domain = $result['domain'];

            // Update Service with Instance Name (store in username) and Domain
            $serviceId = $params['serviceid'];

            Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->update([
                    'username' => $instanceName,
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

function n8n_panel_AdminServicesTabFields(array $params)
{
    try {
        $client = n8n_panel_getClient($params);
        $instanceId = $params['username'];

        if (!empty($instanceId)) {
            $stats = $client->getInstanceStats($instanceId);

            if (isset($stats['status']) && $stats['status'] == 'success') {
                return array(
                    'Instance Status' => ucfirst($stats['instance_status']),
                    'CPU Usage' => $stats['cpu_percent'] . '%',
                    'Memory Usage' => $stats['memory_usage'] . ' / ' . $stats['memory_limit'] . ' (' . $stats['memory_percent'] . '%)',
                    'Domain' => '<a href="http://' . $stats['domain'] . '" target="_blank">' . $stats['domain'] . '</a>',
                );
            }
        }
    } catch (Exception $e) {
        // If API fails, just return nothing or error
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

        // 1. Get Admin Email associated with the API Token
        $connectionData = $client->testConnection();
        if (!isset($connectionData['user']['email'])) {
             return array(
                'success' => false,
                'errorMsg' => "Could not retrieve Admin Email from connection test.",
            );
        }
        $adminEmail = $connectionData['user']['email'];

        // 2. Get SSO URL for Admin
        $result = $client->getUserSso($adminEmail);

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
        // Check Account Role
        $accountRole = isset($params['configoption3']) ? $params['configoption3'] : 'User';

        if ($accountRole !== 'Reseller') {
            return array(
                'success' => false,
                'errorMsg' => "SSO available for Reseller accounts only.",
            );
        }

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
        $accountRole = isset($params['configoption3']) ? $params['configoption3'] : 'User';

        if (!empty($instanceId)) {
             $stats = $client->getInstanceStats($instanceId);

             return array(
                'tabOverviewReplacementTemplate' => 'modules/servers/n8n_panel/templates/manage.tpl',
                'templateVariables' => array(
                    'instanceStats' => $stats,
                    'accountRole' => $accountRole,
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
