<?php

// real basically, we want to load up our code, make sure it works. .. but how do we
// do that? Fuck knows. 

if (php_sapi_name() !== 'cli') {
    throw new \Exception("Invalid use.");
}

// How to do an integration test? Load up a webserver, login an Agent, create a 
// ticket with an attachment.. fark. Might have to build the database as an 
// SQL file and chuck it in a test subfolder.. strewth. 

exit(0); // that's good right?