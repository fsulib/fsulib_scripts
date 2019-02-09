#!/usr/bin/env php
<?php

$input_file = $argv[1];
if (!$argv[1]){
  exit();
}

$output_file = "output_" . $input_file;
$output_file = fopen($output_file, 'w');
$report_url = "http://diginole.lib.fsu.edu/islandora_usage_stats_callbacks/object_stats/";
$csv = array_map('str_getcsv', file($input_file));
$i = 1;

foreach ($csv as $row) {
  $pid = $row[0];
  echo "Row {$i}: Getting stats for {$pid}...\n";
  $json = file_get_contents($report_url . $pid);
  $results = json_decode($json, TRUE);
  $views = @count($results['views']) + @count($results['legacy-views']);
  $downloads = @count($results['downloads']) + @count($results['legacy-downloads']);
  $row[3] = $views;
  $row[4] = $downloads;
  fputcsv($output_file, $row);
  $i++;
}

fclose($output_file);
