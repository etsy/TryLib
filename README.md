# TryLib [![Build Status](https://travis-ci.org/etsy/TryLib.png?branch=master)](https://travis-ci.org/etsy/TryLib)
## "try" your changes on your CI server before committing them to the repository!

TryLib is a simple php library that helps you generate a diff of your working copy and send it to
Jenkins to run the test suite(s) on the latest code patched with your changes.
You can read more about try
on Etsy technical blog [Code As Craft](http://codeascraft.etsy.com/2011/10/11/did-you-try-it-before-you-committed/).

TryLib currently supports **Freestyle** projects, when your test suite consist of a single Jenkins job and **[MasterProject](https://github.com/etsy/jenkins-master-project)** projects, when your test suite consist of multiple Jenkins jobs.

## Example usage:

### try --help output
    $ try -h
    usage: try [options...] [subjob] [subjob] ...

        -h, --help              Show help
        -n, --diff-only         Create diff, but do not send to Hudson
        -v, --verbose           Verbose (show shell commands as they're run)
        -p, --patch ...         Path to patch file to use instead of generating a diff
        -i, --patch-stdin       Read the patch from STDIN instead of a file
        -s, --staged            Use staged changes only to generate the diff
        -b, --branch ...        Remote branch to diff and try against [master]
        -w, --whitelist ...     Generate the patch for only the whitelisted files

        -c, --show-results      Show final try job results
        -P, --show-progress     Print subtasks progressively as they complete
        --extra-param ...       Extra parameters to send to jenkins - format key=value
        -C, --callback ...      Callback string to execute at the end of the try run.

    Use ${status} and ${url} as placeholders for the try build status and url
    Example: --callback 'echo "**Try status : [${status}](${url})**"'

        --jenkinsjob ...        Master Job Name in Jenkins  [try]
        --jenkinsjobprefix ...  Subjobs prefix              [try]
        --jenkinsserver ...     URL to the jenkins server   [localhost:8080]
        --wcpath ...            Working Copy Path           [.]

### Run try on FreeStyle project and show the results in the console.
    $ try --show-results
    Started try-unit-tests #25
    Completed try-unit-tests #25 : SUCCESS

### Run the unit-tests and integration-tests only (MasterProject setup) and show the sub-jobs status as they complete
    $ try --show-progress
    Executing DEFAULT sub-jobs: try-functional-tests try-integration-tests try-unit-tests
    ......... try ( pending )
    ......... try ( pending )
    ......... try ( http://etsyny-l523.local:8080/job/try/13/console )


                      try-unit-tests SUCCESS
                try-functional-tests SUCCESS
               try-integration-tests SUCCESS

    Try Status : SUCCESS (http://etsyny-l523.local:8080/job/try/13)

### Run try and post the results to a github issue
    try --callback "curl -s --user <login>:<password> --request POST --data '{\"body\":\"Try status : [${status}](${url})\"}'" https://github.com/api/v3/repos/etsy/try/issues/1/comments"

### Run try with custom parameters defined in your jenkins job
    try --extra-param foo=bar --extra-param baz=boo

## Try script configuration

Feel free to re-use the boiler plate code in [try](try) and update the parameters to fit your environment.
Depending on your setup (FreeStyle project or master-project), you may have to comment/uncomment some sections of the code - look for **[Jenkins MasterProject only]** in the comments.

    # URL to your jenkins server (without http)
    $jenkins_server = 'localhost:8080';

    # Path to the jenkins cli.jar - download it from http://your.jenkins.instance/cli
    $jenkins_cli_jar = '/usr/bin/jenkins-cli.jar';

    # Jenkins job name
    $default_jenkins_job = 'try';

    # Working copy path (path to your local git repository)
    $repo_path = '.';

**Important** : the try script generates the patch.diff file at the root of the working copy - add it to your **.gitignore** to avoid any conflicts in applying the patch.

## Jenkins configuration
### FreeStyle project

* Create a new *FreeStyle Project*
* Enter the SCM settings
    * make sure to select **Clean after checkout** in the SCM options
    * If you want support for branches, use **${branch}** in the name of branch to checkout
* Tick the *this build is parameterized* checkbox and enter the following parameters:

    **File Parameter**
    File location : patch.diff

    **String Parameter**
    Name : guid
    This is optional - specifying a guid will help if you have many developers 'trying' concurrently

    **String Parameter**
    Name : branch
    This is optional, if you want to build on branches

* Enter the following shell command to apply the patch

        echo "Patch..."
        git apply --verbose patch.diff
        git add .
        # your command to launch your unit tests

### MasterProject

The **[master-project](https://github.com/etsy/jenkins-master-project)** plugin provides a new project type where you can select a list of sub-projects which should be executed in one logical master project.

In order to run try on a master project, each sub-projects must be pre-fixed by the master project name.  An example of setup would be:

    try : master-project
        try-unit-tests : sub-project to run the unit tests
        try-integration-tests : sub-project to run the integrations tests
        try-functional-tests : sub-project to run the functional tests

If your subjobs are not prefixed with your master-job name, set the $jenkinsjobprefix accordingly, either in the the try script or via command line

    $default_jenkins_job_prefix = 'foo';

    ./try --jenkinsjobprefix foo

More info can be found in the [divide and concur](http://codeascraft.etsy.com/2011/04/20/divide-and-concur/) Code As Craft blog entry.

#### Setting up the master-project in Jenkins

* Install the master-project plugin if you don't have it already
* Setup the individual subjobs following the instructions for the FreeStyle project. Each subjobs must be have the same prefix - for example : *try-*. By default, the prefix used it the master project name, but you can override this behavior by specifying the --jenkinsjobprefix
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

## Working with branches (Git)

Try will work with your branches! The below scenarios are supported:

* You are working on **master**:
    * You want to try against master

            try [options] [subjobs]
    * You want to diff and try against a different branch

            try --branch=my_other_branch [options] [subjobs]

* You are working on a **branch tracked remotely**
    * You want to try against that branch

            try [options] [subjobs]
    * You want to diff and try against a different branch (master for example)

            try --branch=master [options] [subjobs]

* You are working on a **local branch not tracked**
    * If the remote has a branch with the same name, it will be used to generate the diff and try against it
    * If the remote does not have a branch with the same name, it will use the default remote : **master**
    * You want to diff and try against a specific remote branch

            try --branch=my_branch [options] [subjobs]

## Working with pre-checks

Prior to generate the diff, you can configure try to run a list of pre-checks.

    $pre_checks = array(
        new TryLib_Precheck_ScriptRunner('/path/to/some/script'),
        new TryLib_Precheck_GitCopyBehind(array('master')),
        new TryLib_Precheck_GitCopyAge(48, 96, $remote_branch)
        new TryLib_Precheck_GitReportUntracked(),
    );

    $repo_manager->runPrechecks($pre_checks);

Some pre-checks will just emit a warning, some can block the try execution.

## Running the tests

You can run the unit test suite with:

    phpunit tests
