<?php

interface TryLib_PreCheck {
    function check($cmd_runner, $location, $upstream);
}
