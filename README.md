# "try" your changes on your CI server before committing them to the repository!

Try is a simple php library that helps you generate a diff of your working copy and send it to 
your CI server (Jenkins only at the moment) to run the test suite. You can read more about try
on Etsy technical blog [Code As Craft](http://codeascraft.etsy.com/2011/10/11/did-you-try-it-before-you-committed/).

An example try script can look like:
    #!/usr/bin/php
    <?php
    
    require_once "Try/Autoload.php";
    
    $jenkins_server = 'your.jenkins.server:8080';
    $jenkins_cli_jar = '/usr/bin/jenkins-cli.jar';
    $jenkins_master_job = 'try';
    $option_parser = new Try_Util_OptionsParser();
    
    $cli = new Try_Cli($jenkins_server, $jenkins_cli_jar, $jenkins_master_job);
    $cli->setOptions($option_parser->getOptions());
    $cli->setUserAndRepoPath(getenv('USER'), '/path/to/your/repo');
    $cli->run();
