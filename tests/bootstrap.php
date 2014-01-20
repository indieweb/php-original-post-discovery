<?php

namespace IndieWeb;

require __DIR__ . '/../vendor/autoload.php';

function fixture($name) {
	return file_get_contents(__DIR__ . '/' . $name);
}
