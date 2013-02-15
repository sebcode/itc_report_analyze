#!/usr/bin/env php
<?php

if (!empty($argv[1])) {
	$appFilter = $argv[1];
} else {
	$appFilter = false;
}

$fxRates = require('fxrates.php');
$myCurrency = $fxRates['myCurrency'];

$data = readReports(glob('*.txt'), $appFilter);

echo formatData($data, $myCurrency);

function readReports($files, $appFilter = false)
{
	$lines = array();

	foreach ($files as $file) {
		$l = file($file);

		if (!count($l)) {
			continue;
		}

		if (strpos($l[0], 'Start Date') !== 0) {
			continue;
		}

		foreach ($l as $line) {
			if (strlen($line) > 1 && is_numeric($line[0])) {
				$lines[] = $line;
			}
		}
	}

	$result = array();

	foreach ($lines as $l) {
		$parts = explode("\t", $l);
		$product = $parts[4];
		$quantity = $parts[5];
		$earnings = $parts[7];
		$currency = $parts[8];

		if ($appFilter !== false && $appFilter != $product) {
			continue;
		}

		if (!isset($result[$product][$currency])) {
			$result[$product][$currency] = array('amount' => 0, 'units' => 0);
		}

		$result[$product][$currency]['amount'] += $earnings;
		$result[$product][$currency]['units'] += $quantity;
	}

	return $result;
}

function formatData($data, $myCurrency)
{
	$result = '';

	$totalTotal = 0;

	foreach ($data as $name => $entry) {
		$result .= "$name\n";

		$total = 0;

		foreach ($entry as $cur => $info) {
			if ($info['amount'] == 0) {
				continue;
			}

			$result .= '  ' . str_pad($info['units'] . ' Units = ', 15, ' ', STR_PAD_LEFT);
			$result .= (string)$info['amount'];
			$result .= " $cur";
			if ($cur == $myCurrency) {
				$total += $info['amount'];
			} else {
				$fxRate = 0;
				$converted = fxConvert($info['amount'], $cur, $myCurrency, $fxRate);
				$total += $converted;
				$result .= ' = ' . round($converted, 4) . ' ' . $myCurrency . " (Rate: $fxRate)";
			}
			$result .= "\n";
		}

		$result .= "Total: " . round($total, 4) . ' ' . $myCurrency;

		$totalTotal += $total;

		$result .= "\n\n";
	}

	$result .= "Overall: " . round($totalTotal, 4) . ' ' . $myCurrency . "\n";

	return $result;
}

function fxConvert($amount, $cur, $myCurrency, &$fxRate)
{
	global $fxRates;

	if (empty($fxRates[$cur])) {
		throw new Exception('No FX rate for currency: '. $cur);
	}

	$fxRate = $fxRates[$cur];

	$converted = $amount / $fxRate;

	return $converted;
}
