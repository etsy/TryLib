<?php

interface RepoManager {

    function getRemoteBranch();

    function generateDiff();

    function runPreChecks(array $preChecks);
}
