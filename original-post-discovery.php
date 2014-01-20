<?php

namespace IndieWeb;

use BarnabyWalters\Mf2 as M;
use Guzzle;
use Mf2;

function cleanString($str) {
	// Replace non breaking spaces with normal spaces
	$str = str_replace(mb_convert_encoding('&nbsp;', 'UTF-8', 'HTML-ENTITIES'), ' ', $str);
	
	$str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
	$str = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
	$str = preg_replace('~\p{C}+~u', '', $str);
	$str = preg_replace(['~\r\n?~', '~[^\P{C}\t\n]+~u'], ["\n", ''], $str);
	
	$str = preg_replace(
		'/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
		'|(?<=^|[\x00-\x7F])[\x80-\xBF]+'.
		'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
		'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
		'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/',
		'�', $str);
	
	$str = preg_replace(
		'/\xE0[\x80-\x9F][\x80-\xBF]'.
		'|\xED[\xA0-\xBF][\x80-\xBF]/S',
		'?',
		$str);
	
	$str = preg_replace_callback('/[\p{So}\p{Cf}\p{Co}\p{Cs}\p{Cn}]/u', function ($string) {
		$result = array();
		
		foreach ((array) $string as $char) {
			$codepoint = unpack('N', iconv('UTF-8', 'UCS-4BE', $char));
			if (is_array($codepoint) && array_key_exists(1, $codepoint))
				$result[] = sprintf('U+%04X', $codepoint[1]);
		}
			
		return implode('', $result);
	}, $str);
	
	return $str;
}

function originalPostUrlFromTwitter($html) {
	$mfs = Mf2\Shim\parseTwitter($html);
	
	$hEntries = M\findMicroformatsByType($mfs, 'h-entry');
	
	if (empty($hEntries))
		return null;
	
	// TODO: should this be getting HTML or plaintext? Hunch is plaintext, easier
	$content = M\getPlaintext($hEntries[0], 'content');
	$content = stripHashtags($content);
	
	if (getTrailingUrl($content))
		return getTrailingUrl($content);
	
	if (getUrlFromPermashortid($content))
		return getUrlFromPermashortid($content);
}

function stripHashtags($content) {
	return trim(preg_replace('/#[a-zA-Z0-9]+/i', '', $content));
}

function getTrailingUrl($content) {
	$parts = explode(' ', $content);
	$lastSegment = array_pop($parts);
	
	if (parse_url($lastSegment, PHP_URL_HOST)) {
		$url = $lastSegment;
	} elseif ($lastSegment[0] == '(' and parse_url(trim($lastSegment, '()'), PHP_URL_HOST)) {
		$url = trim($lastSegment, '()');
	}
	
	if (isset($url)) {
		ob_start();
		$url = web_address_to_uri($url, true);
		ob_end_clean();
		
		return trim(cleanString($url));
	}
	
	return null;
}

function getUrlFromPermashortid($content) {
	$parts = explode(' ', $content);
	$last = array_pop($parts);
	$penultimate = array_pop($parts);
	$cite = $penultimate . ' ' . $last;
	
	if ($cite[0] == '(' and $cite[strlen($cite) - 1] == ')') {
		$domain = 'http://' . explode(' ', trim($cite, '()'))[0];
		$path =  explode(' ', trim($cite, '()'))[1];
		
		if (!parse_url($domain, PHP_URL_HOST))
			return null;
		
		return trim($domain . '/' . $path);
	}
	
	return null;
}

// returns [$url | null, $err]
function discoverOriginalPost($url, Log\LoggerInterface $logger = null) {
	if ($logger === null)
		$logger = new Log\NullLogger();
	
	ob_start();
	$url = web_address_to_uri($url, true);
	ob_end_clean();
	
	$client = new Guzzle\Http\Client();
	
	try {
		$content = $client->get($url)->send()->getBody(true);
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		return [null, $e];
	}
	
	// TODO: follow steps 1 and 2 of iwc.com/original-post-discovery
	
	if (parse_url($url, PHP_URL_HOST) == 'twitter.com'):
		$originalPostUrl = originalPostUrlFromTwitter($content);
	else:
		$originalPostUrl = null;
	endif;
	
	// Check that the document at $originalPostUrl links to $url
	try {
		$response = $client->get($originalPostUrl)->send();
	} catch (Guzzle\Common\Exception\GuzzleException $e) {
		return [null, $e];
	}
	
	$logger->info('Services::original-post fetched:', [
		'given URL' => $originalPostUrl,
		'resolved URL' => $response->getEffectiveUrl(),
		'content type' => $response->getContentType()
	]);
	
	$originalPostUrl = $response->getEffectiveUrl();
	
	if (!stristr($response->getContentType(), 'html'))
		return [null, new Exception("Document at '{$originalPostUrl}' was not transmitted as text/html")];
	
	$originalPostMf = Mf2\parse($response->getBody(true), $originalPostUrl);
	
	// Check that $url is linked to as rel-syndication or u-syndication at $originalPostUrl
	if (isset($originalPostMf['rels']) and in_array($url, $originalPostMf['rels']['syndication'])):
		return $response->getEffectiveUrl();
	elseif (count(M\findMicroformatsByProperty($originalPostMf, 'syndication', $url)) !== 0):
		return $response->getEffectiveUrl();
	else:
		// resolve syndication URLs to see if any syndication URLs resolve to $url
		$mfWithSyndication = M\findMicroformatsByCallable($originalPostMf, function ($mf) {
			return M\hasProp($mf, 'syndication');
		});
		$mfSyndicationUrls = array_reduce($mfWithSyndication, function ($all, $mf) {
			return array_merge($all, M\getPlaintextArray($mf, 'syndication'));
		}, []);
		
		$allRels = array_unique(array_merge($originalPostMf['rels']['syndication'], $mfSyndicationUrls));
		
		$responses = $client->get(array_map(function ($synUrl) use ($client) { return $client->get($synUrl); }, $allRels));
		
		foreach ($responses as $response) {
			if ($response->getEffectiveUrl() === $url) {
				return $response->getEffectiveUrl();
			}
		}
	endif;
	
	return null;
}
