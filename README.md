# "try" your changes on your CI server before committing them to the repository!

Try is a simple php library that helps you generate a diff of your working copy and send it to 
your CI server (Jenkins only at the moment) to run the test suite. You can read more about try
on Etsy technical blog [Code As Craft](http://codeascraft.etsy.com/2011/10/11/did-you-try-it-before-you-committed/).

An example try script can look like:

        #!/bin/env php
        <?php
        
        require_once 'Try/Autoload.php';
        
        function main() {
            $options = Util_OptionsParser::getOptions();
        
            $user = Util_EtsyUtil::getUser();
            $repoPath = Util_EtsyUtil::getRepoPath($user);
        
            $preChecks = array(
                new Try_Precheck_ScriptRunner($repoPath . '/bin/check_file_size'),
                new Try_Precheck_CopyAge()
            );
        
            $cmdRunner = new Try_CommandRunner($options['verbose']);
        
            $repoManager = new Try_RepoManager_Git($repoPath, $cmdRunner);
            $repoManager->runPrechecks($preChecks);
        
            # Generate diff if required
            $patch = $options['patch'];
            if (is_null($patch)) {
                $patch = $repoManager->generateDiff($options['staged-only']);
            }
        
            if ($options['dry-run']) {
                print 'Not sending job to Jenkins (-n) diff is here:' . $patch . PHP_EOL;
                exit(0);
            }
        
            # Send to jenkins
            $jenkinsRunner = new Try_JenkinsRunner(
                'cimaster-dev2.vm.ny4dev.etsy.com:8080',
                '/usr/etsy/jenkins-cli.jar',
                'try',
                $cmdRunner,
                $patch
            );
        
            $jenkinsRunner->setbranch($repoManager->getRemotebranch("master"));
        
            $jenkinsRunner->setSshKey('/home' . $user . '/.ssh/try_id_rsa');
        
            $jenkinsRunner->setUid($user . time());
        
            $jenkinsRunner->setSubJobs($options['jobs']);
        
            $jenkinsRunner->addCallback($options['callback']);
        
            $jenkinsRunner->startJenkinsJob($patch, $options['poll_for_completion']);
        }
        
        main();
