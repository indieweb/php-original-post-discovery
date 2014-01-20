<?php

namespace IndieWeb;

use PHPUnit_Framework_TestCase;

class OriginalPostDiscoveryTest extends PHPUnit_Framework_TestCase {
	public function testOriginalPostUrlFromTwitterFindsParenthesizedLink() {
		$html = fixture('aaronpk-twitter-parenthesis-link.html');
		$this->assertEquals(originalPostUrlFromTwitter($html), 'http://aaron.pk/r4U11');
	}
	
	public function testOriginalPostUrlFromTwitterFindsEllipsisLink() {
		$html = fixture('barnabywalters-twitter-ellipsis-link.html');
		$this->assertEquals(originalPostUrlFromTwitter($html), 'http://waterpigs.co.uk/notes/4U5Ejy/');
	}
	
	public function testOrigianlPostUrlFromTwitterFindsPermashortcitaton() {
		$html = fixture('tantek-twitter-permashortcitation.html');
		$this->assertEquals(originalPostUrlFromTwitter($html), 'http://ttk.me/t4U91');
	}
	
	public function testStripHashtagsRemovesHashtagsFromEndOfContent() {
		$content = 'ha ha ha soup #sofunny #muchwow';
		$this->assertEquals('ha ha ha soup', stripHashtags($content));
	}
}
