#!/usr/bin/php

<?php
/**************************************************************************
 * This script takes two arguments: The username and password             *
 *  to the EDS ftp server.                                                *
 *************************************************************************/

// Set Variables
define ('LOG_FILE', 'guide_exports/log/' . time() . '.log');
define ('SOLR_HOST', '10.0.20.6');
define ('SOLR_PORT', 8983);
define ('SOLR_CORE', 'solr/fsu_lib_web');
define ('FTP_HOST', 'ftp.epnet.com');
define ('FILENAME', '/tmp/' . time() . '.xml');
$username = $argv[1];
$password = $argv[2];

// Connect to Solr Instance
$solr_connection = connect2Solr(SOLR_HOST, SOLR_PORT, SOLR_CORE);

// Figure out how many records we need to retrieve
$exp_query = new SolrQuery('*:*');
$exp_query->setStart(0);
$exp_query->setRows(1);
$exp_query_response = $solr_connection->query($exp_query);
$exp_response = $exp_query_response->getResponse();
$number_of_records = $exp_response->response->numFound;

// Create the local MARCXML file
print_r("\nCreating a MARCXML file with " . $number_of_records . " number of records...");
createMARCXMLFile();

// Fetch each record and pass it to the create add function
for ($count = 0; $count < $number_of_records; $count++) {
  $query = new SolrQuery('*:*');
  $query->setStart($count);
  $query->setRows(1);
  $query_response = $solr_connection->query($query);
  $response = $query_response->getResponse();
  addMARCXMLRecord($response->response->docs[0]);
}

// Close the local MARCXML file
closeMARCXMLFile();

// Connect to FTP host
$ftp_connection = ftp_connect(FTP_HOST) or die("Could not connect to FTP_HOST");
$login = ftp_login($ftp_connection, $username, $password);
$mode = ftp_pasv($ftp_connection, 1);
ftp_chdir($ftp_connection, "full");

// upload the file
if (ftp_put($ftp_connection, 'full_upload.xml', FILENAME, FTP_BINARY)) {
  echo "successfully uploaded" . FILENAME . ".\n";
} else {
  echo "There was a problem while uploading " . FILENAME . "\n";
}

// Close FTP Connection
ftp_close($ftp_connection);

// Delete the local file
unlink(FILENAME);

/***********************************
 * Function Definitions Start Here *
 ***********************************/

// Create the MARCXML File
function createMARCXMLFile()
{
  $marcxml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $marcxml .= '<marc:collection xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
  $marcxml .= ' xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">' . "\n";
  file_put_contents(FILENAME, $marcxml);
}

// Takes an SimpleXMLElement Guide Object and transforms it into a SOLR Document
function addMARCXMLRecord($solr_record)
{
  date_default_timezone_set('America/New_York');
  $marcxml = '<marc:record>' . "\n";
  $marcxml .= '  <marc:leader>00000nmi a2200097u  4500</marc:leader>'. "\n";
  $marcxml .= '  <marc:controlfield tag="001">' . $solr_record->id . '</marc:controlfield>' . "\n";
  $marcxml .= '  <marc:controlfield tag="007">cr||||||||||||</marc:controlfield>' . "\n";
  $marcxml .= '  <marc:controlfield tag="008">' . date("ymd") . 's' . date("Y")  . '||||flu|||||o||d||||||||eng|d</marc:controlfield>' . "\n";
  $marcxml .= '  <marc:datafield tag="245" ind1="0" ind2="0">' . "\n";
  $marcxml .= '    <marc:subfield code="a">' . $solr_record->tm_title[0] . '</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '  <marc:datafield tag="264" ind1=" " ind2="1">' . "\n";
  $marcxml .= '    <marc:subfield code="b">Florida State University Libraries,</marc:subfield>' . "\n";
  $marcxml .= '    <marc:subfield code="c">' . date("Y") . '.</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '  <marc:datafield tag="300" ind1=" " ind2=" ">' . "\n";
  $marcxml .= '    <marc:subfield code="a">website</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '  <marc:datafield tag="520" ind1=" " ind2=" ">' . "\n";
  $marcxml .= '    <marc:subfield code="a">' . strip_tags($solr_record->{'tm_body$value'}[0]) . '</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '  <marc:datafield tag="699" ind1=" " ind2=" ">' . "\n";
  $marcxml .= '    <marc:subfield code="a">' . substr(strip_tags($solr_record->{'tm_body$value'}[0]), 0, 150) . '...</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '  <marc:datafield tag="856" ind1="4" ind2="0">' . "\n";
  $marcxml .= '    <marc:subfield code="3">Website</marc:subfield>' . "\n";
  $marcxml .= '    <marc:subfield code="u">' . $solr_record->ss_url . '</marc:subfield>' . "\n";
  $marcxml .= '    <marc:subfield code="y">Connect to online content</marc:subfield>' . "\n";
  $marcxml .= '  </marc:datafield>' . "\n";
  $marcxml .= '</marc:record>' . "\n";

  //Write to file in temp directory
  file_put_contents(FILENAME, $marcxml, FILE_APPEND);
  print_r("Successfully added record id: " . $solr_record->id . "...\n");
}

function closeMARCXMLFile()
{
  $marcxml = '</marc:collection>' . "\n";
  file_put_contents(FILENAME, $marcxml, FILE_APPEND);
}

// Creates a solr connection
function connect2Solr($hostname, $port, $path)
{
  $options = array
  (
    'hostname' => $hostname,
    'port' => $port,
    'path' => $path,
  );

  $solr_connection = new SolrClient($options);
  return $solr_connection;
}

// Writes to Log File
function write2Log($message)
{
  $myfile = fopen(LOG_FILE, 'a') or die("Unable to open log file!");
  fwrite($myfile, "\n" . $message . " - " . time());
  fclose($myfile);
}
