# "try" your changes on your CI server before committing them to the repository!

Try is a simple php library that helps you generate a diff of your working copy and send it to 
your CI server (Jenkins only at the moment) to run the test suite. You can read more about try
on Etsy technical blog [Code As Craft](http://codeascraft.etsy.com/2011/10/11/did-you-try-it-before-you-committed/).

## try script

An example try script can look like:

    #!/usr/bin/php
    <?php
    
    require_once "Try/Autoload.php";
    
    $jenkins_server = 'your.jenkins.server:8080';
    $jenkins_cli_jar = '/usr/bin/jenkins-cli.jar';
    $jenkins_master_job = 'try'; //This is the name of your master-project in jenkins
    $option_parser = new Try_Util_OptionsParser();
    
    $cli = new Try_Cli($jenkins_server, $jenkins_cli_jar, $jenkins_master_job);
    $cli->setOptions($option_parser->getOptions());
    $cli->setUserAndRepoPath(getenv('USER'), '/path/to/your/repo');
    $cli->run();

## Example usage:

### Show help

	try -h
	USAGE: try [options] [subjobs ...]

	OPTIONS:
	    -h --help                   Show help
	    -n --diff-only              Create diff, but do not send to Hudson
	    -v --verbose                Verbose (show shell commands as they're run)
	    -p|--patch=</path/to/diff>  Don't generate diffs; use custom patch file instead
	    -P --show-progress          Print subtasks progressively as they complete (implies c)
	    -s --staged                 Use staged changes only to generate the diff
	    -c|--callback <string>      Callback string to execute at the end of the try run.
	                                Use ${status} and ${url} as placeholders for the try build status and url
	                                Example: -C 'echo "**Try status : [${status}](${url})**"'

### Run the unit-tests and orm-tests only and show status
	try --show-progress unit-tests orm-tests

### Run try and post the results to a github issue
	try --callback="curl -s --user <login>:<password> --request POST --data '{\"body\":\"Try status : [${status}](${url})\"}'" https://github.com/api/v3/repos/etsy/try/issues/1/comments"


## Jenkins setup

**try** currently support only [master-project](https://github.com/etsy/jenkins-master-project) top level project. 
The **master-project** plugin provides a new project type where you can select a list of sub-projects which should be executed in one logical master project.

An example of setup would be:
	try : master-project
		try-unit-tests : sub-project to run unit tests
		try-orm-tests : sub-project to run unit tests
		try-integration-tests : sub-project to run unit tests

More info in the [divide and concur](http://codeascraft.etsy.com/2011/04/20/divide-and-concur/) Code As Craft blog entry.

### Setting up the master-project in Jenkins

* Install the master-project plugin if you don't have it already
* Create a new *Master Project* and name it **try**
* Tick the *Allow building and hiding of select sub-jobs* checkbox
* Specify the *Default Project names*, eg : try-unit-tests try-orm-tests try-integration-tests
* Tick the *this build is parameterized* checkbox and enter the following parameters:

	**File Parameter**  
	File location : patch.diff

	**String Parameter**  
	Name : guid
	This is optional - specifying a guid will help if you have many developers 'trying' concurrently
	
	**String Parameter**  
	Name : branch
	This is optional, if you want to build on branches
	
* In the *Sub-Jobs*, tick all the sub-projects that should be enabled with the master project ( at least all the default projects, but you can select more jobs)
		
### Setting up the sub-project(s) in Jenkins

* Create a new freestyle project
* Tick the *this build is parameterized* checkbox and enter the following parameters:

	**File Parameter**  
	File location : patch.diff

	**String Parameter**  
	Name : guid
	This is optional - specifying a guid will help if you have many developers 'trying' concurrently
	
	**String Parameter**  
	Name : branch
	This is optional, if you want to build on branches

* If you want try support for branches, use the $branch parameters in the SCM settings.
* Make sure to select *Clean after checkout* in the SCM options
* Enter the following shell command to apply the patch
	echo "Patch..."
    patch --verbose -p0 -f -i patch.diff
    git add .


## Working with branches (Git)

Try will work with your branches! The below scenarios are supported:

* You are working on **master**:
	* You want to try against master --> run ***try [options] [subjobs]***
	* You want to diff and try against a different branch --> run ***try --branch=my_other_branch [options] [subjobs]***

* You are working on a **branch tracked remotely**
	* You want to try against that branch --> run ***try [options] [subjobs]***
	* You want to diff and try against a different branch (master for example) --> run ***try --branch=master [options] [subjobs]***  

* You are working on a **local branch not tracked**
	* If the remote has a branch with the same name, it will be used to generate the diff and try against it
	* If the remote does not have a branch with the same name, it will use the default remote : **master**
	* You want to diff and try against a specific remote branch --> run ***try --branch=my_branch_ [options] [subjobs]**