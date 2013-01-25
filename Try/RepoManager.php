<?php

interface Try_RepoManager {

    function getRemoteBranch();

    function generateDiff();

    function runPreChecks(array $pre_checks);
}
