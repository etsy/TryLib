<?php

namespace TryLib\TryRunner;

/**
 * A standard set of command-line options for use with TryRunner.
 */
final class Options {

    const USAGE_SPEC = "
try [options...] [subjob] [subjob] ...
--
h,help                Show help
n,diff-only           Create diff, but do not send to Hudson
v,verbose             Verbose (show shell commands as they're run)
p,patch=              Path to patch file to use instead of generating a diff
U,lines-of-context=   Generate a diff with n lines of context (like git-diff's -U option)
i,patch-stdin         Read the patch to use from STDIN instead of a file
s,staged              Use staged changes only to generate the diff
w,whitelist,safelist= Generate the patch for only the safelisted files
b,branch=             Remote branch to diff and try against [\$default_remote_branch]

c,show-results        Show final try job results
P,show-progress       Print subtasks progressively as they complete
extra-param=          Extra parameters to send to jenkins - format key=value
C,callback=           Callback string to execute at the end of the try run.
                      Use \${status} and \${url} as placeholders for the try build status and url
                      Example: --callback 'echo \"**Try status : [\${status}](\${url})**\"'

jenkinsjob=           Master Job Name in Jenkins  [\$default_jenkins_job]
jenkinsjobprefix=     Subjobs prefix              [\$default_jenkins_job_prefix]
jenkinsserver=        URL to the jenkins server   [\$jenkins_server]
wcpath=               Working Copy Path           [\$default_wc_path]
";

    private function __construct() {}  // Do not instantiate.

    public static function parse(
            $argv,
            $jenkins_server,
            $default_jenkins_job,
            $default_jenkins_job_prefix = null,
            $default_wc_path = null,
            $default_remote_branch = "master") {

        $default_jenkins_job_prefix = $default_jenkins_job_prefix ?: $default_jenkins_job;
        $default_wc_path = $default_wc_path ?: ".";

        $formattedUsageSpec = self::USAGE_SPEC;
        $formattedUsageSpec = str_replace("\$default_jenkins_job_prefix", $default_jenkins_job_prefix, $formattedUsageSpec);
        $formattedUsageSpec = str_replace("\$default_jenkins_job", $default_jenkins_job, $formattedUsageSpec);
        $formattedUsageSpec = str_replace("\$jenkins_server", $jenkins_server, $formattedUsageSpec);
        $formattedUsageSpec = str_replace("\$default_wc_path", $default_wc_path, $formattedUsageSpec);

        // In the case of $default_remote_branch, we want to optionally remove the default
        // altogether.
        if (is_null($default_remote_branch)) {
            $formattedUsageSpec = str_replace(" [\$default_remote_branch]", "", $formattedUsageSpec);
        } else {
            $formattedUsageSpec = str_replace("\$default_remote_branch", $default_remote_branch, $formattedUsageSpec);
        }

        $parser = new \TryLib\Util\PHPOptions\Options($formattedUsageSpec);

        return $parser->parse($argv);
    }
}
