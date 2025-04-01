<?php
//@route /:firstname/:lastname [GET, POST]
//@auth supersecret
echo "Hi " . $_REQUEST['firstname'] . ' ' . $_REQUEST['lastname'] . "!<br>";
