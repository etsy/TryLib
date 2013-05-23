<?php

interface TryLib_RepoManager {

    function getRemoteBranch();

    function generateDiff();

    function runPreChecks(array $pre_checks);
}
