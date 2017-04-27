<?php

namespace TryLib;

interface Precheck {
    function check($cmd_runner, $location, $upstream);
}
