<?php
/**
 * Load Javascrip
 */
use OCP\Util;
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
    Util::addScript('mautilcompression', 'compress' );
    Util::addStyle('mautilcompression', 'style' );
});
