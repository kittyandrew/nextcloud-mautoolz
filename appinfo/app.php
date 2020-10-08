<?php
/**
 * Load Javascrip
 */
use OCP\Util;
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
    Util::addScript('mautilcompression', 'mautilcompression' );
    Util::addStyle('mautilcompression', 'style' );
});
