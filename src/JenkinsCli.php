<?php

namespace TryLib;

use Exception;

const SERVER_VERSION = 'x-jenkins';

/**
 * Abstract class for handling different jenkins cli versions.
 * The server url is used to get version information and look up correct
 * jenkins-cli jar version.
 * Currently the jenkins-cli jar is deployed to clients via chef.
 */
abstract class JenkinsCli
{
    private const JENKINS_CLI_JAR_VERSION_TABLE = [
        "2.19.3" => "/usr/etsy/jenkins-cli.jar",
        "2.303.1" => "/usr/etsy/jenkins-cli-2.303.1.jar"
    ];

    /**
     *  Function to obtain Jenkins server info from given server url.
     *  Jenkins servers return info that can be used by clients to connect properly.
     *  This info is returned in http headers whose names start with 'x-'
     *  Example response:
     *  Array
     * (
     *   [x-content-type-options] => nosniff
     *   [x-hudson-theme] => default
     *   [x-hudson] => 1.395
     *   [x-jenkins] => 2.19.3
     *   [x-jenkins-session] => 091ef581
     *   [x-hudson-cli-port] => 58999
     *   [x-jenkins-cli-port] => 58999
     *   [x-jenkins-cli2-port] => 58999
     *   [x-frame-options] => sameorigin
     *   [x-instance-identity] => MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAp0vIS8uMTaf6Hep/4SdDkjz2719m+RlzCbDlpYNUvXzT6GNgE3/lnLmXic1wIn2Ym7B+aHkQjm/5bP33VXuob/x+R6dX3iUe93zi7YRG0KUeHXCgglm+y1BymxldqNzFyFVJ22D24Qnt7qoATEaoXLa4VkQ1ZuIaBzaVq0qNZYR7zShQvS7TRD+itqxFGFEKEWGwkh0sFgenSIFpwy9MmOhY11i/+A2VdA5K/KcPnmnW3AMByNUVoaigRkHOCAU2mnBsfFngTpdxd2SEGCxosewsfi/aCLRFU1INCFXgngt/V7sba4U2ADV8E/kS1rTdHDkWVQQMbiSCwKDPXQ30uQIDAQAB
     *   [x-ssh-endpoint] => try.etsycloud.com:41108
     * )
     */

    private static function get_jenkins_server_info($server_url)
    {

        $headers = [];
        $curl = curl_init($server_url);
        curl_setopt($curl, CURLOPT_URL, $server_url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // see https://stackoverflow.com/a/41135574/16345588
        curl_setopt(
            $curl,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;
                $header_key = strtolower(trim($header[0]));
                if (substr($header_key, 0, 2) == 'x-') {
                    $headers[$header_key] = trim($header[1]);
                }


                return $len;
            }
        );
        echo "Getting server info for ". $server_url . PHP_EOL ;
        $response = curl_exec($curl);
        return $headers;
    }
    public static function get_jenkins_cli_info($server_url)
    {
        try {
            $server_info = self::get_jenkins_server_info($server_url);
            if (array_key_exists(SERVER_VERSION, $server_info)) {
                $server_version = $server_info[SERVER_VERSION];
                $jenkins_cli_jar = self::JENKINS_CLI_JAR_VERSION_TABLE[$server_version];
                
            };
        } catch (Exception $e) {
            echo "Cannot get server info, falling back to NULL.". PHP_EOL;
            $jenkins_cli_jar = NULL;
            $server_version = NULL;
        };
        echo "jenkins_cli_jar = ". $jenkins_cli_jar . PHP_EOL;
        echo "server_version = ". $server_version . PHP_EOL;
        return [$jenkins_cli_jar, $server_version ];
    }
    public static function get_jenkins_cli_command($jenkins_cli_jar, $server_url, $user, $command)
    {
        // Get server info from jar name
        $server_version = NULL;
        foreach(self::JENKINS_CLI_JAR_VERSION_TABLE as $version => $jar){
            if ($jenkins_cli_jar == $jar){
                $server_version = $version;
                break;
            }
        }
        // Usage: java -jar jenkins-cli.jar [-s URL] command [opts...] args...
        switch ($server_version) {
            case "2.19.3":
                $cli_options = "";
                break;
            case "2.303.1":
                $cli_options = sprintf("-ssh -user %s -logger OFF ", $user);
                break;
            default:
                $cli_options = "";
        };
        $cmd_format = "java -jar %s %s -s %s %s";
        $cmd = sprintf(
            $cmd_format,
            $jenkins_cli_jar,
            $cli_options,
            $server_url,
            $command
        );
        return $cmd;
    }
}
