#!/usr/bin/php

<?php

// Set Variables
define ('LOG_FILE', 'guide_exports/log/' . time() . '.log');
define ('SOLR_HOST', '10.0.20.6');
define ('SOLR_PORT', 8983);
define ('SOLR_CORE', 'solr/fsu_lib_web');

// Connect to Solr Instance
$solr_connection = connect2Solr(SOLR_HOST, SOLR_PORT, SOLR_CORE);

// Figure out how many records we need to retrieve
$exp_query = new SolrQuery('*:*');
$exp_query->setStart(0);
$exp_query->setRows(1);
$exp_query_response = $solr_connection->query($exp_query);
$exp_response = $exp_query_response->getResponse();
$number_of_records = $exp_response->response->numFound;

// Fetch each record and pass it to the create function
for ($count = 0; $count < 1; $count++) {
  $query = new SolrQuery('*:*');
  $query->setStart($count);
  $query->setRows(1);
  $query_response = $solr_connection->query($query);
  $response = $query_response->getResponse();
  createMARCXMLFile($response->response->docs[0]);
  print_r($number_of_records);
}

/***********************************
 * Function Definitions Start Here *
 ***********************************/

// Takes an SimpleXMLElement Guide Object and transforms it into a SOLR Document
function createMARCXMLFile($solr_record)
{
  date_default_timezone_set('America/New_York');
  $marcxml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $marcxml .= '<marc:record xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:marc="http://www.loc.gov/MARC21/slim"' . "\n";
  $marcxml .= '  xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">' . "\n";
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
  print_r($marcxml);
  /*print_r($solr_record);*/
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
