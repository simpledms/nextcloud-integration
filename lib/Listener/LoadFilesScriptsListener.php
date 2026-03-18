<?php

declare(strict_types=1);

namespace OCA\SimpleDMS\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\SimpleDMS\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadFilesScriptsListener implements IEventListener {
    public function handle(Event $event): void {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        Util::addScript(Application::APP_ID, 'simpledms-files-action', 'files');
    }
}
