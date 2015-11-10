#!/usr/bin/env php

<?php

ini_set('display_errors',1);  
error_reporting(E_ALL);

$submissions = simplexml_load_string(shell_exec("drush ne-export --format=xml --type=faculty_scholarship_submission"));

foreach ($submissions as $submission) {
  
  // Get drupal/node specific info
  $node_id = $submission->nid;
  $machine_title = $submission->title;
  $time_created = $submission->created;
  $time_last_modified = $submission->changed;
 
  // Submitter Info
  $submitter_first_name = $submission->field_first_name->und->n0->value;
  $submitter_last_name = $submission->field_last_name->und->n0->value;
  $submitter_email = $submission->field_email->und->n0->value;
  $submitter_fsu_dept = $submission->field_fsu_department->und->n0->value; // Check this against the taxonomy later

  // Get publication metadata
  $publication_date = $submission->field_publication_date->und->n0->value;
  $publication_title = $submission->field_publication_title->und->n0->value;
  $publication_volume = $submission->field_publication_volume->und->n0->value;
  $publication_issue = $submission->field_publication_issue->und->n0->value;
  $publication_page_range = $submission->field_publication_page_range->und->n0->value;

  // Get author info
  /*
  foreach ($submission->field_scholarly_author->und as $author) {
    echo $author->children
  }
  */
  
  // Document status
  $submission_type = $submission->field_submission_type->und->n0->value;
  $submission_status = $submission->field_submission_status->und->n0->value;
  $submission_note = $submission->field_note->und->n0->value;
  $embargo_period = $submission->field_embargo_period->und->n0->value;
  $identifier = $submission->field_item_standard_identifier->und->n0->value;

  // Document metadata
  $abstract = $submission->field_abstract->und->n0->value;
  $title = $submission->field_submission_title->und->n0->value; // Parse for subtitles and nonsorts

  // Create MODS object
  //$xml = new SimpleXMLElement('<mods></mods>');
  $xml = new SimpleXMLElement('<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/" xmlns:flvc="info:flvc/manifest/v1" xsi:schemaLocation="http://www.loc.gov/standards/mods/v3/mods-3-4.xsd" version="3.4"></mods>');

  // Build title
  $xml->addChild('titleInfo');
  $xml->titleInfo->addAttribute('lang', 'eng');
  $xml->titleInfo->addChild('title', $title); // Parse out for display later with subtitles/nonsorts

  // Build authors (WHERE ARE THE AUTHORS?!?!)

  // Static elements
  $xml->addChild('typeOfResource', 'text');
  $xml->addChild('genre', 'text');
  $xml->genre->addAttribute('authority', 'rdacontent');
  $xml->addChild('physicalDescription');
  $rda_media = $xml->physicalDescription->addChild('form', 'computer');
  $rda_media->addAttribute('authority', 'rdamedia'); 
  $rda_media->addAttribute('type', 'RDA media terms');
  $rda_carrier = $xml->physicalDescription->addChild('form', 'online resource');
  $rda_carrier->addAttribute('authority', 'rdacarrier'); 
  $rda_carrier->addAttribute('type', 'RDA carrier terms');
  $xml->physicalDescription->addChild('extent', '1 online resource');
  $xml->physicalDescription->addChild('digitalOrigin', 'born digital');
  $xml->physicalDescription->addChild('internetMediaType', 'application/pdf');
  $xml->addChild('recordInfo');
  $xml->recordInfo->addChild('recordCreationDate', date('Y-m-d'))->addAttribute('encoding', 'w3cdtf');
  $xml->recordInfo->addChild('descriptionStandard', 'rda');
  $xml->addChild('identifier', $identifier)->addAttribute('type', 'IID');
  $flvc = $xml->addChild('extension')->addChild('flvc:flvc', '', 'info:flvc/manifest/v1');
  $flvc->addChild('flvc:owningInstitution', 'FSU');
  $flvc->addChild('flvc:submittingInstitution', 'FSU');

  // Format XML and write to file
  $output = fopen("{$machine_title}.xml", "w");
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  echo $dom->saveXML();
  fwrite($output, $dom->saveXML());
  fclose($output);

}

?>
