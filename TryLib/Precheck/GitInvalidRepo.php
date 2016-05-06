<?php

class TryLib_Precheck_GitInvalidRepo implements Trylib_Precheck {

	protected $valid_origin_urls = [];

	public function __construct(array $valid_origin_urls = []) {

		$this->valid_origin_urls = $valid_origin_urls;
	}

	public function check($cmd_runner, $location, $upstream) {

		$ret = false;
		$remote_origin = $this->getRemote($cmd_runner);

		//no valid origins are set
		if(empty($this->valid_origin_urls)) {

			$ret = true;
		} else if( in_array($remote_origin, $this->valid_origin_urls) ) {

			$ret = true;
		} else {
	
			//regex
			foreach($this->valid_origin_urls as $idx => $pattern) {

				if( preg_match($pattern, $remote_origin) ) {
					$ret = true;
					break;	
				}
			}
		}

		if(!$ret) {

			$cmd_runner->terminate("Try is being run from an invalid repository.");
		}
		return $ret;	
	}

	protected function getRemote(TryLib_CommandRunner $cmd_runner) {
	
		$cmd_runner->run('git config --get remote.origin.url');
		return $cmd_runner->getOutput();
	}
}
