<?php

namespace TryLib;

interface RepoManager {

    function getRemoteBranch();

    function generateDiff();

    function runPreChecks(array $pre_checks);
}
