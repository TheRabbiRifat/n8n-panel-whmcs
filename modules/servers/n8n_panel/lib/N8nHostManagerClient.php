<?php

namespace WHMCS\Module\Server\N8nPanel;

use Exception;

class N8nHostManagerClient
{
    private $apiUrl;
    private $apiToken;
    private $verifySsl;

    public function __construct($apiUrl, $apiToken, $verifySsl = true)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiToken = $apiToken;
        $this->verifySsl = $verifySsl;
    }

    private function request($method, $endpoint, $data = [])
    {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!$this->verifySsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = "Request failed with status $httpCode";
            if (isset($decodedResponse['message'])) {
                $errorMessage .= ": " . $decodedResponse['message'];
            } elseif (isset($decodedResponse['error'])) {
                 $errorMessage .= ": " . $decodedResponse['error'];
            }
            throw new Exception($errorMessage);
        }

        return $decodedResponse;
    }

    public function testConnection()
    {
        return $this->request('GET', '/connection/test');
    }

    public function getSystemStats()
    {
        return $this->request('GET', '/system/stats');
    }

    public function createInstance($packageId, $name, $version = 'latest')
    {
        return $this->request('POST', '/instances/create', [
            'package_id' => $packageId,
            'name' => $name,
            'version' => $version
        ]);
    }

    public function getInstanceStats($name)
    {
        return $this->request('GET', '/instances/' . $name . '/stats');
    }

    public function startInstance($name)
    {
        return $this->request('POST', '/instances/' . $name . '/start');
    }

    public function stopInstance($name)
    {
        return $this->request('POST', '/instances/' . $name . '/stop');
    }

    public function suspendInstance($name)
    {
        return $this->request('POST', '/instances/' . $name . '/suspend');
    }

    public function unsuspendInstance($name)
    {
        return $this->request('POST', '/instances/' . $name . '/unsuspend');
    }

    public function terminateInstance($name)
    {
        return $this->request('POST', '/instances/' . $name . '/terminate');
    }

    public function upgradeInstance($name, $packageId)
    {
        return $this->request('POST', '/instances/' . $name . '/upgrade', [
            'package_id' => $packageId
        ]);
    }

    public function getPackages()
    {
        return $this->request('GET', '/packages');
    }

    public function getPackage($id)
    {
        return $this->request('GET', '/packages/' . $id);
    }

    public function createReseller($name, $username, $email, $password, $instanceLimit = null)
    {
        $data = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password
        ];

        if ($instanceLimit !== null) {
            $data['instance_limit'] = $instanceLimit;
        }

        return $this->request('POST', '/resellers', $data);
    }

    public function updateReseller($username, $data)
    {
        return $this->request('PUT', '/resellers/' . $username, $data);
    }

    public function suspendReseller($username)
    {
        return $this->request('POST', '/resellers/' . $username . '/suspend');
    }

    public function unsuspendReseller($username)
    {
        return $this->request('POST', '/resellers/' . $username . '/unsuspend');
    }

    public function deleteReseller($username)
    {
        return $this->request('DELETE', '/resellers/' . $username);
    }

    public function getResellerStats($username)
    {
        return $this->request('GET', '/resellers/' . $username . '/stats');
    }

    public function getResellerSso($username)
    {
        return $this->request('POST', '/resellers/' . $username . '/sso');
    }

    public function getUserSso($username)
    {
        return $this->request('POST', '/users/sso', [
            'username' => $username
        ]);
    }
}
