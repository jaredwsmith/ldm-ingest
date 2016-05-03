#!/usr/bin/php
<?php
/*
 * LDM Product Ingestor
 * Command-line tool
 * Main entry point for LDM ingest. This hands off to a factory which generates a class for specific products.
 * Many thanks to @blairblends, @edarc, and the Updraft team for help and inspiration
 */

//
// Execution time
//

$time_start = microtime(true);

//
// Configuration
//

include('conf/chswx.conf.php');

//
// Utilities and libraries
//

// Base utilities
include('inc/Utils.class.php');

// Bring in the abstract class definition for NWSProduct.
include('inc/NWSProduct.class.php');
// And its factory
include('inc/NWSProductFactory.class.php');

// Get the file path from the command line.
// #11: Pipable stuff, arguments, etc.

// First, backward compatibility for file input
$shortopts = "f::";
$options = getopt($shortopts);
var_dump($options);
if(!empty($options['f'])) {
    $file_path = $options['f'];
    Utils::log("Ingest has begun. Filename: " . $file_path);
    // Bring in the file
    $m_text = file_get_contents($file_path);
}
// Next, if a file is not specified, require these options and pipe input
else {
    $shortopts = "w:o:t:a:c::";
    $longopts = array(
        'wmo:',
        'office:',
        'time:',
        'afos:',
        'corrections::'
    );

    $options = getopt($shortopts,$longopts);
    if($options) {
        Utils::log("Ingest has begun from STDIN ({$options['a']})");
        stream_set_blocking(STDIN,0);
        $m_text = stream_get_contents(STDIN);
    }
    else {
        die("Aborting parse");
    }
}

// Send to the factory to parse the product.
$product_obj = NWSProductFactory::get_product(Utils::sanitize($m_text));

// Only here as a debugging measure.
var_dump($product_obj);

// JSON-encode the product object to stuff into the DB.
// TODO: Stuff this into the RethinkDB
$product_json = json_encode($product_obj);

var_dump($product_json);

$time_end = microtime(true);
$time = $time_end - $time_start;
Utils::log("Ingest and relay complete. Execution time: $time seconds");