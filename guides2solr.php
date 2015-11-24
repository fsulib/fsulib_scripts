#!/usr/bin/php

<?php

/*************************************************************************
 * This script takes one argument: The path to the libguides export file *
 *************************************************************************/

// Set Variables
define ('LOG_FILE', '/home/vagrant/guides_log/' . time() . '.log');
define ('SOLR_HOST', 'localhost');
define ('SOLR_PORT', 8983);
define ('SOLR_CORE', 'solr/fsu_lib_web');
$count = 0;

// Open LibGuides Export file and retrieve XML in a string variable
$clean_xml = cleanXMLFile($argv[1]);

// Create a SimpleXMLElement object using the XML string
$xml_object = createSimpleXMLObject($clean_xml);

// Connect to Solr Instance
$solr_connection = connect2Solr(SOLR_HOST, SOLR_PORT, SOLR_CORE);

echo "\nIngesting LibGuides Export...";

foreach ($xml_object->guides->guide as $guide) {
  
  foreach ($guide->pages->page as $page) {
  
    // Create a Solr document using the XML Object information
    $solr_document = createSolrDocument($guide, $page);
    $update_response = $solr_connection->addDocument($solr_document);
    write2Log($update_response->getRawResponse());
    echo ".";
    $count++;
  }
}

echo "\n\n" . $count . " Documents successfully ingested.";
echo "\nLog can be found at: " . LOG_FILE . "\n";
echo "\nHappy searching!\n\n";

/***********************************
 * Function Definitions Start Here *
 ***********************************/

// Takes an SimpleXMLElement Guide Object and transforms it into a SOLR Document
function createSolrDocument($guide, $page)
{
  $doc = new SolrInputDocument();
   
  if(isset($page->id)) {
    $doc->addField('id', "rg_" . $page->id);
  } 
  
  $doc->addField('index_id', 'fsu_research_guides');
  
  $content_value = getContentFromPage($page);
  $doc->addField('tm_body$value', $content_value);
  
  if(isset($guide->name) && isset($page->name)) {
    $title = $guide->name . " - " . $page->name;
    $doc->addField('tm_title', $title);
  }
  
  $doc->addField('ss_type', 'research_guide');
  
  if(isset($page->url)) {
    $doc->addField('ss_url', $page->url);
  }
  
  $doc->addField('content', $content_value);
  
  return $doc;
}

// Takes a SimpleXMLElement Page Object and retrieves the content values
function getContentFromPage($page) 
{
  $content_value = '';
  
  foreach($page->boxes->box as $box) {
    foreach($box->assets->asset as $asset) {
      if(isset($asset->description)) {
        $content_value .= $asset->description;
      }
    }
  }
  
  return $content_value;
}

// Takes file path as input and returns an XML string without invalid characters
function cleanXMLFile($file_path)
{ 
  $raw_xml = file_get_contents($file_path);

  if ($raw_xml) {
    echo "Successfully opened file " . $file_path . "...\n";
  }

  $safe_xml = preg_replace('/[\x0-\x1f\x7f-\x9f]/u', ' ', $raw_xml);

  return $safe_xml;
}

// Takes valid XML string as input and returns a SimpleXMLElement object
function createSimpleXMLObject($xml)
{
  libxml_use_internal_errors(true);

  $xml_object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_PARSEHUGE);
  
  if ($xml_object) {
    echo "SimpleXMLElement object successfully created...\n";
  } else {
    echo "\nFailed loading XML\n";
    foreach(libxml_get_errors() as $error) {
      echo "Error code " . $error->code . " in line " . $error->line . ": " . $error->message;
    }
  }
  
  return $xml_object;
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