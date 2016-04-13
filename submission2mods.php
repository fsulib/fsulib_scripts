#!/usr/bin/env php
<?php

require_once('/var/www/html/fsulib_scripts/assets/fpdf181/fpdf.php');
require_once('/var/www/html/fsulib_scripts/assets/FPDI-1.6.1/fpdi.php');

ini_set('display_errors',1);  
error_reporting(E_ALL);
date_default_timezone_set('America/Indianapolis');
$version = 1;
$package_dir = "/var/www/html/sites/default/files/scholarship/packages";
$email = "bjbrown@fsu.edu";

// Grab field values only if they exist, and set FALSE otherwise
function get_optional_field_value($field) {
  if (isset($field->und->n0->value)) {
    return $field->und->n0->value;
  }
  else {
    return FALSE;
  }
}

$submissions = simplexml_load_string(shell_exec("drush --root=/var/www/html ne-export --format=xml --type=faculty_scholarship_submission"));

foreach ($submissions as $submission) {
  if ($submission->field_submission_status->und->n0->value == "approved") {


    //
    // Extract raw data from nodes
    //////////////////////////////
    $submission_title = $submission->field_submission_title->und->n0->value;
    $submission_subtitle = get_optional_field_value($submission->field_submission_subtitle);
    echo "\nExtracting data for $submission_title\n";
    $submission_node_id = $submission->nid;
    $submission_machine_title = $submission->title;
    $submission_time_created = $submission->created;
    $submission_time_last_modified = $submission->changed;
    $submission_submitter_first_name = $submission->field_first_name->und->n0->value;
    $submission_submitter_last_name = $submission->field_last_name->und->n0->value;
    $submission_submitter_email = $submission->field_email->und->n0->value;
    $submission_submitter_fsu_dept = $submission->field_fsu_department->und->n0->target_id;
    $submission_type = $submission->field_submission_type->und->n0->value;
    $submission_authors = $submission->field_scholarly_author->und->children();
    $submission_abstract = get_optional_field_value($submission->field_abstract);
    $submission_doi = get_optional_field_value($submission->field_item_standard_identifier);
    $submission_keywords = get_optional_field_value($submission->field_keywords);
    $submission_embargo_period = get_optional_field_value($submission->field_embargo_period);
    $submission_visibility = $submission->field_visibility->und->n0->value;
    $submission_publication_date = $submission->field_publication_date_string->und->n0->value;
    $submission_publication_title = get_optional_field_value($submission->field_publication_title);
    $submission_publication_volume = get_optional_field_value($submission->field_publication_volume);
    $submission_publication_issue = get_optional_field_value($submission->field_publication_issue);
    $submission_publication_page_range = get_optional_field_value($submission->field_publication_page_range);
    $submission_publication_note = get_optional_field_value($submission->field_publication_note);
    $submission_preferred_citation = get_optional_field_value($submission->field_preferred_citation);
    $submission_grant_number = get_optional_field_value($submission->field_grant_number);
    $submission_filename = $submission->field_primary_file_upload->und->n0->filename;
    $submission_note_to_submission_staff = get_optional_field_value($submission->field_note_to_submission_staff);


    //
    // Format extracted data
    ////////////////////////

    // Build IID from machine title
    $submission_iid = "FSU_libsubv{$version}_{$submission_machine_title}";

    // Grab submitter dept by taxonomy ID
    $submitter_dept = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select name from omega_taxonomy_term_data where tid={$submission_submitter_fsu_dept}\""));

    // Parse title
    $nonsorts = array("A", "An", "The");
    $title_array = explode(" ", $submission_title);
    if (in_array($title_array[0], $nonsorts)) {
      $nonsort = $title_array[0];
      $title = implode(" ", array_slice($title_array, 1));
    }
    else {
      $nonsort = FALSE;
      $title = $submission_title;
    }
    $cptitle = ($submission_subtitle ? "{$submission_title}: {$submission_subtitle}" : "{$submission_title}");

    // Build array of authors
    unset($author_array);
    $author_array = array();
    foreach ($submission_authors as $a) {
      $author = array();
      $author['first_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_first_name_value from omega_field_data_field_first_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['middle_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_middle_name_value from omega_field_data_field_middle_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['last_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_last_name_value from omega_field_data_field_last_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['institution'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_institution_value from omega_field_data_field_institution where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author_array[] = $author;
    }

    // Get IP embargo status 
    $ip_embargo = ($submission_visibility == 0 ? "No" : "Yes");
  
    // Create computed cron embargo status
    if ($submission_embargo_period == "U") {
      echo "$submission_machine_title has an unknown embargo date. Please fix.\n";
      $embargo_msg = "Unknown";
    }
    elseif ($submission_embargo_period == "") {
      $embargo_msg = "No embargo"; 
    }
    elseif (strtotime($submission_publication_date)) {
      // Actually create the computed embargo expirty date
      $embargo_expiry_date = date("Y-m-d", strtotime("+{$submission_embargo_period} months", strtotime($submission_publication_date)));
      if (strtotime($embargo_expiry_date) > time()) {
        $embargo_msg = "Embargo until $embargo_expiry_date";
      }
      else {
        // If its already expired, then we don't care
        $embargo_msg = "No embargo";
      }
    }
    else {
      echo "$submission_publication_date is not a valid YYYY-MM-DD date. Please fix.\n";
      $embargo_msg = "Invalid publication date";
    }
    $cpdate = date('Y', strtotime($submission_publication_date));

    echo "Building MODS for $submission_title\n";

    //
    // Create MODS object
    /////////////////////
    $xml = new SimpleXMLElement('<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/" xmlns:flvc="info:flvc/manifest/v1" xsi:schemaLocation="http://www.loc.gov/standards/mods/v3/mods-3-4.xsd" version="3.4"></mods>');

    // Build title
    $xml->addChild('titleInfo');
    $xml->titleInfo->addAttribute('lang', 'eng');
    $xml->titleInfo->addChild('title', htmlspecialchars($title));
    if ($nonsort) { $xml->titleInfo->addChild('nonSort', htmlspecialchars($nonsort)); }
    if ($submission_subtitle) { $xml->titleInfo->addChild('subTitle', htmlspecialchars($submission_subtitle)); }

    // Build authors
    unset($cpauthors);
    unset($cpauthors_array);
    foreach ($author_array as $author) {
      $a = $xml->addChild('name');
      $a->addAttribute('type', 'personal');
      $a->addAttribute('authority', 'local');
      if ($author['middle_name']) {
        $a->addChild('namePart', htmlspecialchars("{$author['first_name']} {$author['middle_name']}"))->addAttribute('type', 'given');
        $cpauthors_array[] = "{$author['first_name']} {$author['middle_name']} {$author['last_name']}";
      }
      else {
        $a->addChild('namePart', htmlspecialchars("{$author['first_name']}"))->addAttribute('type', 'given');
        $cpauthors_array[] = "{$author['first_name']} {$author['last_name']}";
      }
      $a->addChild('namePart', htmlspecialchars("{$author['last_name']}"))->addAttribute('type', 'family');

      if ($author['institution']) {
        $a->addChild('affiliation', htmlspecialchars("{$author['institution']}"));
      }
      $a->addChild('role');
      $r1 = $a->role->addChild('roleTerm', 'author'); 
      $r1->addAttribute('authority', 'rda');
      $r1->addAttribute('type', 'text');
      $r2 = $a->role->addChild('roleTerm', 'aut'); 
      $r2->addAttribute('authority', 'marcrelator');
      $r2->addAttribute('type', 'code');
    }
    if (count($cpauthors_array) == 1) {
      $cpauthors = implode("", $cpauthors_array);
    }
    elseif (count($cpauthors_array) == 2) {
      $cpauthors = implode(" and ", $cpauthors_array);
    }
    else {
      $cpauthors = implode(", ", array_slice($cpauthors_array, 0, -2)) . ", " . implode(" and ", array_slice($cpauthors_array, -2)); 
    }

    // Origin Info
    $xml->addChild('originInfo');
    $dateIssued = $xml->originInfo->addChild('dateIssued', htmlspecialchars($submission_publication_date));
    $dateIssued->addAttribute('encoding', 'w3cdtf');
    $dateIssued->addAttribute('keyDate', 'yes');

    // Abstract
    if ($submission_abstract) { $xml->addChild('abstract', htmlspecialchars($submission_abstract)); }
    // Add identifiers (IID, DOI)
    $xml->addChild('identifier', $submission_iid)->addAttribute('type', 'IID');
    if ($submission_doi) { $xml->addChild('identifier', $submission_doi)->addAttribute('type', 'DOI'); }

    // Add related item
    if ($submission_publication_title) {
      $xml->addChild('relatedItem')->addAttribute('type', 'host');
      $xml->relatedItem->addChild('titleInfo');
      $xml->relatedItem->titleInfo->addChild('title', htmlspecialchars($submission_publication_title));

      if ($submission_publication_volume OR $submission_publication_issue OR $submission_publication_page_range) {
        $xml->relatedItem->addChild('part');

        if ($submission_publication_volume) { 
          $volume = $xml->relatedItem->part->addChild('detail');
          $volume->addAttribute('type', 'volume');
          $volume->addChild('number', htmlspecialchars($submission_publication_volume));
          $volume->addChild('caption', 'vol.');  
        }
        if ($submission_publication_issue) { 
          $issue = $xml->relatedItem->part->addChild('detail');
          $issue->addAttribute('type', 'issue');
          $issue->addChild('number', htmlspecialchars($submission_publication_issue));
          $issue->addChild('caption', 'iss.');  
        }
        if ($submission_publication_page_range) { 
          $e = $xml->relatedItem->part->addChild('extent');
          $e->addAttribute('unit', 'page');
          if (strpos($submission_publication_page_range, '-')) {
            $page_range_array = explode('-', $submission_publication_page_range);
            $xml->relatedItem->part->extent->addChild('start', htmlspecialchars($page_range_array[0]));
            $xml->relatedItem->part->extent->addChild('end', htmlspecialchars($page_range_array[1]));
          }
          else {
            $e->addChild('start', htmlspecialchars($submission_publication_page_range));
          }
        }

      }
    }

    // Notes (keywords, publication note, preferred citation)
    if ($submission_keywords) { $xml->addChild('note', htmlspecialchars($submission_keywords))->addAttribute('displayLabel', 'Keywords'); }
    if ($submission_publication_note) { $xml->addChild('note', htmlspecialchars($submission_publication_note))->addAttribute('displayLabel', 'Publication Note'); }
    if ($submission_preferred_citation) { $xml->addChild('note', htmlspecialchars($submission_preferred_citation))->addAttribute('displayLabel', 'Preferred Citation'); }
    if ($submission_grant_number) { $xml->addChild('note', htmlspecialchars($submission_grant_number))->addAttribute('displayLabel', 'Grant Number'); }

    // Add FLVC extensions
    $flvc = $xml->addChild('extension')->addChild('flvc:flvc', '', 'info:flvc/manifest/v1');
    $flvc->addChild('flvc:owningInstitution', 'FSU');
    $flvc->addChild('flvc:submittingInstitution', 'FSU');

    // Add static elements
    $xml->addChild('typeOfResource', 'text');
    $xml->addChild('genre', 'text')->addAttribute('authority', 'rdacontent');
    $xml->addChild('language');
    $l1 = $xml->language->addChild('languageTerm', 'English');
    $l1->addAttribute('type', 'text');
    $l2 = $xml->language->addChild('languageTerm', 'eng');
    $l2->addAttribute('type', 'code');
    $l2->addAttribute('authority', 'iso639-2b');
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


    echo "Writing MODS for $submission_title\n";

    // Format XML and write to file
    $package_path = "{$package_dir}/{$submission_iid}";
    if (file_exists($package_path)) {
      shell_exec("rm -rf {$package_path}");
    }
    shell_exec("mkdir {$package_path}");
    $output = fopen("{$package_path}/{$submission_iid}.xml", "w");
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    fwrite($output, $dom->saveXML());
    fclose($output);

    echo "Building coverpage for $submission_title\n";
    
    // Add coverpage
    shell_exec("cp /var/www/html/sites/default/files/scholarship/{$submission_filename} {$package_path}/orig.pdf");
    $pdf = new FPDI();
    $pdf->AddPage('P', 'Letter');
    $pdf->setSourceFile("/var/www/html/fsulib_scripts/assets/coverpage.pdf");
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Times');
    $pdf->setFontSize(14);
    $pdf->SetXY(25, 55);
    $pdf->Write(0, $cpdate);
    $pdf->setFontSize(26);
    $pdf->SetXY(25, 60);
    $pdf->MultiCell(0, 10, $cptitle, 0, 'L');
    $pdf->setFontSize(14);
    $pdf->setLeftMargin(25);
    $pdf->SetY($pdf->GetY() + 3);
    $pdf->MultiCell(0, 5, $cpauthors, 0, 'L');
    $pdf->Output('F', "{$package_path}/coverpage.pdf");
    shell_exec("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile={$package_path}/{$submission_iid}.pdf {$package_path}/coverpage.pdf {$package_path}/orig.pdf");
    

    // Create zip
    shell_exec("cd {$package_path}; zip -r {$submission_iid}.zip {$submission_iid}.xml {$submission_iid}.pdf");
    shell_exec("cp {$package_path}/{$submission_iid}.zip {$package_path}.zip");
    shell_exec("rm -rf {$package_path}");

    echo "Sending email for $submission_title\n";

    // Email repo manager
    $subject = "New ingest package: ${submission_iid}";
    $message = <<<BODY
<p>You have one new scholarship submission to ingest into DigiNole:</p>
<p>
<strong>Type:</strong> ${submission_type}<br/>
<strong>Title:</strong> ${submission_title}<br/>
<strong>IID:</strong> ${submission_iid}<br/>
<strong>Department:</strong> ${submitter_dept}<br/>
<strong>Publication date:</strong> ${submission_publication_date} + {$submission_embargo_period}<br/>
<strong>Date embargo:</strong> ${embargo_msg}<br/>
<strong>IP embargo</strong>: {$ip_embargo}<br/>
<strong>Note to submission staff:</strong><br/>
{$submission_note_to_submission_staff}<br/>
</p>
<p>Download the zipped ingest package <a href="https://www.lib.fsu.edu/sites/default/files/scholarship/packages/{$submission_iid}.zip">here</a>.</p>
BODY;
    $headers = 'From: lib-ir@fsu.edu' . "\r\n" . 
               'MIME-Version: 1.0' . "\r\n" .
               'Content-type: text/html; charset=UTF-8' . "\r\n";
    mail($email, $subject, $message, $headers);
  }
}
?>
