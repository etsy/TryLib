<?php

/**
 * @deprecated
 */
class TryLib_Precheck_GitWarnOnBlacklisted implements TryLib_Precheck {

    function __construct(array $blocklist, $safelist = null, $staged = false) {
		$warning = "[DEPRECATION] 'TryLib_Precheck_GitWarnOnBlacklisted' is deprecated. ";
		$warning .= "Please use 'TryLib_Precheck_GitWarnOnBlocklisted' instead." . PHP_EOL;
		trigger_error($warning);
	    $this->check = new TryLib_Precheck_GitWarnOnBlocklisted($blocklist, $safelist, $staged);
	}

    function check($cmd_runner, $repo_path, $upstream) {
		$this->check->check($cmd_runner, $repo_path, $upstream);

	}

}
