#!/usr/bin/env php
<?php

require_once('./assets/fpdf181/fpdf.php');
require_once('./assets/FPDI-1.6.1/fpdi.php');

$pdf = new FPDI();
$pdf->AddPage('P', 'Letter');
$pdf->setSourceFile("./assets/coverpage.pdf");
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Times');

// Date
$pdf->setFontSize(14);
$pdf->SetXY(25, 55);
$pdf->Write(0, '2011');

// Title
$pdf->setFontSize(26);
$pdf->SetXY(25, 60);
$pdf->MultiCell(0, 10, 'Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report', 0, 'L');

// Authors
$pdf->setFontSize(14);
$pdf->setLeftMargin(25);
$pdf->SetY($pdf->GetY() + 3);
$pdf->MultiCell(0, 5, 'Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report Faculty Senate Library Committee - Task Force on Scholarly Communications: Final Report', 0, 'L');

$pdf->Output(F,'filename.pdf');
//shell_exec("gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=final.pdf test/coverpage.pdf test/test.pdf");

?>
