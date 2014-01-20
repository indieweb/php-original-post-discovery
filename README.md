# php-original-post-discovery

A set of PHP functions for determining the canonical URL for a post, given a POSSEd copy.

## Usage

Install using [Composer](https://getcomposer.org) `./composer.phar require indieweb/original-post-discovery:dev-master`.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

list($url, $err) = IndieWeb\discoverOriginalPost('https://twitter.com/BarnabyWalters/status/423465842148671488');
if ($err !== null) {
	// handle HTTP errors here
}

// do stuff (e.g. auto-fill in-reply-to form controls) with $url

```

### Other functions

* `string $str = cleanString($str)` cleans up a bunch of weird encoding and character issues which can occur, specifically converting non-breaking space codepoints into normal spaces to handle some Twitter.com bugs
* `string|null $url = originalPostUrlFromTwitter($html)` is a pure function for parsing HTML from Twitter.com and looking in it for trailing URLs
* `string $str = stripHashtags($str)` removes hashtags from a string
* `string|null $url = getTrailingUrl($str)` finds parenthesised (`text text. (http://example.com)`) or ellipsis (`text text… http://example.com`) trailing URLs in a string
* `string|null $str = getUrlFromPermashortid($str)` looks for a trailing permashortid (`(cctld.me id)`) and converts it into a URL (assumes HTTP)

## Testing

A small PHPUnit test suite is provided — if making contributions please at least ensure that all the existing tests pass before/after your changes are made. If you could add new tests to cover the code you added that would be great too.

## Version History

### 0.1.0 2014-01-20
* Initial extraction from Taproot, readme and basic test suite
